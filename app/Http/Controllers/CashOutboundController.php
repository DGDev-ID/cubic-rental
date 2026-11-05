<?php

namespace App\Http\Controllers;

use App\Models\CashOutbound;
use App\Models\Employee;
use App\Http\Requests\CashOutboundRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashOutboundController extends Controller
{
    public function index(Request $request): Response
    {
        $query = CashOutbound::with('employee');
        if ($request->date) {
            $query->whereDate('date', $request->date);
        }

        return Inertia::render('CashOutbound/Index', [
            'outbounds' => $query
                ->orderByDesc('date')
                ->orderByDesc('created_at')
                ->paginate(20)->withQueryString(),
            'employees' => Employee::where('status', 'active')->orderBy('name')->get(),
            'total_this_month' => CashOutbound::whereYear('date', now()->year)
                ->whereMonth('date', now()->month)
                ->sum('nominal'),
            'filters' => $request->only('date'),
        ]);
    }

    public function store(CashOutboundRequest $request): RedirectResponse
    {
        CashOutbound::create($request->validated());
        return back()->with('success', 'Pengeluaran berhasil dicatat.');
    }

    public function update(CashOutboundRequest $request, CashOutbound $cashOutbound): RedirectResponse
    {
        $cashOutbound->update($request->validated());
        return back()->with('success', 'Pengeluaran berhasil diperbarui.');
    }

    public function destroy(CashOutbound $cashOutbound): RedirectResponse
    {
        $cashOutbound->delete();
        return back()->with('success', 'Pengeluaran berhasil dihapus.');
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $query = CashOutbound::with('employee');

        $periodLabel = 'Semua Periode';
        if ($request->date) {
            $query->whereDate('date', $request->date);
            $periodLabel = Carbon::parse($request->date)->translatedFormat('d F Y');
        } elseif ($request->month) {
            [$y, $m] = explode('-', $request->month);
            $query->whereYear('date', (int) $y)->whereMonth('date', (int) $m);
            $periodLabel = Carbon::createFromDate((int) $y, (int) $m, 1)->translatedFormat('F Y');
        } elseif ($request->year) {
            $query->whereYear('date', (int) $request->year);
            $periodLabel = 'Tahun ' . $request->year;
        }

        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        $outbounds = $query->orderBy('date', 'asc')->orderBy('created_at', 'asc')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pengeluaran Kas');

        $headers = [
            'No',
            'Tanggal',
            'Keterangan',
            'Operator / Kasir',
            'Nominal (Rp)',
        ];
        $colCount = count($headers);
        $lastCol = Coordinate::stringFromColumnIndex($colCount);

        // Header branding
        $sheet->setCellValue('A1', config('app.name', 'Cubic') . ' – LAPORAN PENGELUARAN KAS');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DC2626']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('A2', 'Periode: ' . $periodLabel);
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EF4444']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('A3', 'Diekspor pada: ' . now()->format('d/m/Y H:i:s'));
        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->getStyle('A3')->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '64748B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);

        $headerRow = 5;
        foreach ($headers as $i => $h) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$col}{$headerRow}", $h);
        }
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => '7F1D1D'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FECACA']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FCA5A5']]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(24);

        $row = 6;
        $totalNominal = 0;
        $borderStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
        ];
        $normFill = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']];
        $altFill  = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF1F2']];
        $numFmt   = '#,##0';

        foreach ($outbounds as $idx => $o) {
            $fill = $idx % 2 === 0 ? $normFill : $altFill;
            $nominal = (float) $o->nominal;
            $totalNominal += $nominal;

            $sheet->setCellValue("A{$row}", $idx + 1);
            $sheet->setCellValue("B{$row}", $o->date ? Carbon::parse($o->date)->format('d/m/Y') : '-');
            $sheet->setCellValue("C{$row}", $o->notes);
            $sheet->setCellValue("D{$row}", $o->employee->name ?? '-');
            $sheet->setCellValue("E{$row}", $nominal);

            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray(array_merge(['fill' => $fill], $borderStyle));
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode($numFmt);

            $row++;
        }

        // Summary Row
        $sheet->setCellValue("A{$row}", 'TOTAL PENGELUARAN');
        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->setCellValue("E{$row}", (float) $totalNominal);

        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DC2626']],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'B91C1C']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode($numFmt);
        $sheet->getRowDimension($row)->setRowHeight(24);

        $widths = [
            'A' => 8,  // No
            'B' => 16, // Tanggal
            'C' => 45, // Keterangan
            'D' => 24, // Operator
            'E' => 22, // Nominal (Rp)
        ];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $fileName = 'pengeluaran_kas_' . date('Y-m-d_H-i-s') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        $respHeaders = [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control'       => 'max-age=0',
        ];

        return new StreamedResponse(
            function () use ($writer) { $writer->save('php://output'); },
            200,
            $respHeaders
        );
    }
}
