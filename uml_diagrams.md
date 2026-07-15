# UML Diagrams — Cubic Gaming Lounge Rental System

> Dihasilkan dari analisis migration, models, controllers, dan services project `rental-ps`.

---

## 1. Use Case Diagram

```mermaid
%%{init: {'theme': 'base', 'themeVariables': {'primaryColor': '#1e3a5f', 'primaryTextColor': '#fff', 'primaryBorderColor': '#2d6a9f', 'lineColor': '#aaa', 'background': '#0d1b2a'}}}%%
graph LR
    Admin(["👤 Admin"])
    Operator(["👤 Operator"])
    System(["⚙️ System"])

    subgraph UC_AUTH ["🔐 Authentication"]
        UC1["Login"]
        UC2["Logout"]
        UC3["Edit Profile"]
    end

    subgraph UC_MASTER ["📦 Master Data Management"]
        UC4["Kelola Karyawan (CRUD)"]
        UC5["Kelola Console (CRUD)"]
        UC6["Kelola Game (CRUD)"]
        UC7["Kelola FnB Item (CRUD)"]
        UC8["Kelola FnB Addon (CRUD)"]
        UC9["Kelola Package (CRUD)"]
    end

    subgraph UC_RENTAL ["🎮 Rental Management"]
        UC10["Mulai Rental Baru"]
        UC11["Lihat Detail Rental (Live)"]
        UC12["Tambah Waktu (Extension)"]
        UC13["Tambah FnB ke Rental"]
        UC14["Hapus FnB dari Rental"]
        UC15["Selesaikan Rental (Finish)"]
        UC16["Bayar Rental"]
        UC17["Cetak Struk (Receipt)"]
        UC18["Lihat Riwayat Rental"]
        UC19["Ekspor Riwayat ke CSV"]
    end

    subgraph UC_RESERVATION ["📅 Reservation Management"]
        UC20["Buat Reservasi"]
        UC21["Update Reservasi"]
        UC22["Batalkan Reservasi"]
        UC23["Konversi Reservasi → Rental"]
    end

    subgraph UC_FNB ["🍔 FnB Order Standalone"]
        UC24["Buat Order FnB"]
        UC25["Lihat Detail Order FnB"]
        UC26["Bayar Order FnB"]
        UC27["Batalkan Order FnB"]
    end

    subgraph UC_CASH ["💰 Keuangan"]
        UC28["Catat Pengeluaran Kas"]
        UC29["Lihat Riwayat Pengeluaran"]
        UC30["Edit Pengeluaran Kas"]
        UC31["Hapus Pengeluaran Kas"]
    end

    subgraph UC_DASHBOARD ["📊 Dashboard & Monitor"]
        UC32["Lihat Dashboard (Ringkasan)"]
        UC33["Monitor Rental Aktif (Live)"]
        UC34["Room Monitor"]
    end

    Admin --> UC1
    Admin --> UC2
    Admin --> UC3
    Admin --> UC4
    Admin --> UC5
    Admin --> UC6
    Admin --> UC7
    Admin --> UC8
    Admin --> UC9
    Admin --> UC18
    Admin --> UC19
    Admin --> UC29
    Admin --> UC30
    Admin --> UC31
    Admin --> UC32

    Operator --> UC1
    Operator --> UC2
    Operator --> UC3
    Operator --> UC10
    Operator --> UC11
    Operator --> UC12
    Operator --> UC13
    Operator --> UC14
    Operator --> UC15
    Operator --> UC16
    Operator --> UC17
    Operator --> UC18
    Operator --> UC20
    Operator --> UC21
    Operator --> UC22
    Operator --> UC23
    Operator --> UC24
    Operator --> UC25
    Operator --> UC26
    Operator --> UC27
    Operator --> UC28
    Operator --> UC32
    Operator --> UC33
    Operator --> UC34

    System --> UC33
```

---

## 2. ERD (Entity Relationship Diagram)

```mermaid
erDiagram
    USERS {
        bigint id PK
        string nama
        string username UK
        string password
        string email
        enum role "admin,operator"
        timestamp created_at
        timestamp updated_at
    }

    EMPLOYEES {
        bigint id PK
        string name
        string phone
        string position
        enum status "active,inactive"
        timestamp deleted_at
        timestamp created_at
        timestamp updated_at
    }

    CONSOLES {
        bigint id PK
        string name UK
        enum type "regular,vip,vvip,suite"
        decimal price_per_hour
        text description
        enum status "available,occupied,maintenance"
        timestamp deleted_at
        timestamp created_at
        timestamp updated_at
    }

    GAMES {
        bigint id PK
        string name
        string genre
        string platform
        boolean is_available
        timestamp deleted_at
        timestamp created_at
        timestamp updated_at
    }

    CONSOLE_GAMES {
        bigint console_id FK
        bigint game_id FK
    }

    PACKAGES {
        bigint id PK
        string name
        text description
        int duration_hours
        decimal price
        boolean is_available
        timestamp deleted_at
        timestamp created_at
        timestamp updated_at
    }

    RENTALS {
        bigint id PK
        string transaction_code UK
        string customer_name
        bigint console_id FK
        bigint employee_id FK
        bigint package_id FK
        enum rental_type "open_time,duration,package"
        enum status "running,finished,paid,half_paid,cancelled"
        timestamp started_at
        timestamp ended_at
        timestamp scheduled_end_at
        decimal rental_amount
        decimal fnb_amount
        decimal extra_amount
        decimal total_amount
        decimal paid_amount
        text notes
        timestamp deleted_at
        timestamp created_at
        timestamp updated_at
    }

    RENTAL_EXTENSIONS {
        bigint id PK
        bigint rental_id FK
        int added_minutes
        decimal additional_price
        text notes
        timestamp created_at
        timestamp updated_at
    }

    RENTAL_PAYMENTS {
        bigint id PK
        bigint rental_id FK
        enum method "cash,qris,split,half_paid"
        decimal amount
        text notes
        timestamp created_at
        timestamp updated_at
    }

    FNB_ITEMS {
        bigint id PK
        string name
        enum category "food,drink,snack"
        decimal price
        int stock
        boolean is_available
        timestamp deleted_at
        timestamp created_at
        timestamp updated_at
    }

    FNB_ADDONS {
        bigint id PK
        bigint fnb_item_id FK
        string name
        decimal price
        boolean is_available
        timestamp deleted_at
        timestamp created_at
        timestamp updated_at
    }

    RENTAL_FNB_ITEMS {
        bigint id PK
        bigint rental_id FK
        bigint fnb_item_id FK
        int qty
        decimal unit_price
        decimal subtotal
        json addons
        decimal addons_price
        timestamp created_at
        timestamp updated_at
    }

    FNB_ORDERS {
        bigint id PK
        string code UK
        string customer_name
        bigint employee_id FK
        bigint console_id FK
        decimal total_amount
        enum status "pending,paid"
        enum payment_method "cash,qris,transfer"
        text notes
        timestamp paid_at
        timestamp created_at
        timestamp updated_at
    }

    FNB_ORDER_ITEMS {
        bigint id PK
        bigint fnb_order_id FK
        bigint fnb_item_id FK
        int qty
        decimal unit_price
        decimal subtotal
        json addons
        decimal addons_price
        timestamp created_at
        timestamp updated_at
    }

    RESERVATIONS {
        bigint id PK
        bigint console_id FK
        bigint employee_id FK
        string customer_name
        string customer_phone
        timestamp reserved_at
        float duration_hours
        text notes
        enum status "pending,confirmed,cancelled,converted"
        bigint rental_id FK
        timestamp created_at
        timestamp updated_at
    }

    CASH_OUTBOUNDS {
        bigint id PK
        decimal nominal
        text notes
        bigint employee_id FK
        date date
        timestamp deleted_at
        timestamp created_at
        timestamp updated_at
    }

    %% Relationships
    CONSOLES ||--o{ RENTALS : "has many"
    CONSOLES }|--|{ GAMES : "console_games (M:N)"
    CONSOLES ||--o{ RESERVATIONS : "has many"
    CONSOLES ||--o{ FNB_ORDERS : "has many"

    EMPLOYEES ||--o{ RENTALS : "operates"
    EMPLOYEES ||--o{ RESERVATIONS : "handles"
    EMPLOYEES ||--o{ FNB_ORDERS : "creates"
    EMPLOYEES ||--o{ CASH_OUTBOUNDS : "records"

    PACKAGES ||--o{ RENTALS : "used in"

    RENTALS ||--o{ RENTAL_EXTENSIONS : "extends"
    RENTALS ||--o{ RENTAL_FNB_ITEMS : "has FnB"
    RENTALS ||--o{ RENTAL_PAYMENTS : "has payments"
    RENTALS ||--o| RESERVATIONS : "converted from"

    FNB_ITEMS ||--o{ RENTAL_FNB_ITEMS : "ordered in rental"
    FNB_ITEMS ||--o{ FNB_ADDONS : "has addons"
    FNB_ITEMS ||--o{ FNB_ORDER_ITEMS : "ordered standalone"

    FNB_ORDERS ||--o{ FNB_ORDER_ITEMS : "contains"

    CONSOLE_GAMES }o--|| CONSOLES : ""
    CONSOLE_GAMES }o--|| GAMES : ""
```

---

## 3. Class Diagram

```mermaid
classDiagram
    direction TB

    class User {
        +bigint id
        +string nama
        +string username
        +string email
        +string password
        +string role
        +timestamps()
    }

    class Employee {
        +bigint id
        +string name
        +string phone
        +string position
        +string status
        +softDeletes()
        +rentals() HasMany
        +reservations() HasMany
        +fnbOrders() HasMany
        +cashOutbounds() HasMany
    }

    class Console {
        +bigint id
        +string name
        +string type
        +decimal price_per_hour
        +string description
        +string status
        +softDeletes()
        +games() BelongsToMany
        +rentals() HasMany
        +activeRental() HasOne
        +reservations() HasMany
        +fnbOrders() HasMany
    }

    class Game {
        +bigint id
        +string name
        +string genre
        +string platform
        +bool is_available
        +softDeletes()
        +consoles() BelongsToMany
    }

    class Package {
        +bigint id
        +string name
        +text description
        +int duration_hours
        +decimal price
        +bool is_available
        +softDeletes()
        +rentals() HasMany
    }

    class Rental {
        +bigint id
        +string transaction_code
        +string customer_name
        +bigint console_id
        +bigint employee_id
        +bigint package_id
        +string rental_type
        +string status
        +datetime started_at
        +datetime ended_at
        +datetime scheduled_end_at
        +decimal rental_amount
        +decimal fnb_amount
        +decimal extra_amount
        +decimal total_amount
        +decimal paid_amount
        +text notes
        +softDeletes()
        +console() BelongsTo
        +employee() BelongsTo
        +package() BelongsTo
        +extensions() HasMany
        +fnbItems() HasMany
        +payments() HasMany
        +getDurationMinutesAttribute() int
        +getRemainingMinutesAttribute() int
        +getIsOvertimeAttribute() bool
        +recalculateTotal() void
    }

    class RentalExtension {
        +bigint id
        +bigint rental_id
        +int added_minutes
        +decimal additional_price
        +text notes
        +timestamps()
        +rental() BelongsTo
    }

    class RentalPayment {
        +bigint id
        +bigint rental_id
        +string method
        +decimal amount
        +text notes
        +timestamps()
        +rental() BelongsTo
    }

    class FnbItem {
        +bigint id
        +string name
        +string category
        +decimal price
        +int stock
        +bool is_available
        +softDeletes()
        +addons() HasMany
        +rentalFnbItems() HasMany
        +orderItems() HasMany
    }

    class FnbAddon {
        +bigint id
        +bigint fnb_item_id
        +string name
        +decimal price
        +bool is_available
        +softDeletes()
        +fnbItem() BelongsTo
    }

    class RentalFnbItem {
        +bigint id
        +bigint rental_id
        +bigint fnb_item_id
        +int qty
        +decimal unit_price
        +decimal subtotal
        +json addons
        +decimal addons_price
        +timestamps()
        +rental() BelongsTo
        +fnbItem() BelongsTo
    }

    class FnbOrder {
        +bigint id
        +string code
        +string customer_name
        +bigint employee_id
        +bigint console_id
        +decimal total_amount
        +string status
        +string payment_method
        +text notes
        +datetime paid_at
        +timestamps()
        +employee() BelongsTo
        +console() BelongsTo
        +items() HasMany
    }

    class FnbOrderItem {
        +bigint id
        +bigint fnb_order_id
        +bigint fnb_item_id
        +int qty
        +decimal unit_price
        +decimal subtotal
        +json addons
        +decimal addons_price
        +timestamps()
        +fnbOrder() BelongsTo
        +fnbItem() BelongsTo
    }

    class Reservation {
        +bigint id
        +bigint console_id
        +bigint employee_id
        +string customer_name
        +string customer_phone
        +datetime reserved_at
        +float duration_hours
        +text notes
        +string status
        +bigint rental_id
        +timestamps()
        +console() BelongsTo
        +employee() BelongsTo
        +rental() BelongsTo
    }

    class CashOutbound {
        +bigint id
        +decimal nominal
        +text notes
        +bigint employee_id
        +date date
        +softDeletes()
        +employee() BelongsTo
    }

    class RentalService {
        +create(array data) Rental
        +addTime(Rental rental, array data) RentalExtension
        +addFnb(Rental rental, array items) void
        +finish(Rental rental) Rental
        +pay(Rental rental, array payments) void
        +getActiveRentals() Collection
        +getFinishedUnpaidRentals() Collection
    }

    class DashboardService {
        +getSummary() array
        +getActiveRentals() Collection
    }

    %% Controller Classes
    class RentalController {
        -RentalService rentalService
        +index() Response
        +store(Request) RedirectResponse
        +show(Rental) Response
        +addTime(Request, Rental) RedirectResponse
        +addFnb(Request, Rental) RedirectResponse
        +removeFnb(Rental, RentalFnbItem) RedirectResponse
        +finish(Rental) RedirectResponse
        +payment(Rental) Response
        +pay(Request, Rental) RedirectResponse
        +receipt(Rental) Response
        +history(Request) Response
        +exportExcel(Request) StreamedResponse
    }

    class FnbOrderController {
        +index() Response
        +store(Request) RedirectResponse
        +show(FnbOrder) Response
        +pay(Request, FnbOrder) RedirectResponse
        +destroy(FnbOrder) RedirectResponse
    }

    class ReservationController {
        -RentalService rentalService
        +index(Request) Response
        +store(Request) RedirectResponse
        +update(Request, Reservation) RedirectResponse
        +destroy(Reservation) RedirectResponse
        +convert(Reservation) RedirectResponse
    }

    %% Relationships
    Console "1" --> "*" Rental : has many
    Console "1" --> "*" Reservation : has many
    Console "1" --> "*" FnbOrder : has many
    Console "*" --> "*" Game : M:N via console_games

    Employee "1" --> "*" Rental : operates
    Employee "1" --> "*" Reservation : handles
    Employee "1" --> "*" FnbOrder : creates
    Employee "1" --> "*" CashOutbound : records

    Package "1" --> "*" Rental : used by

    Rental "1" --> "*" RentalExtension : extended by
    Rental "1" --> "*" RentalFnbItem : has FnB items
    Rental "1" --> "*" RentalPayment : paid through
    Rental "1" --> "0..1" Reservation : converted from

    FnbItem "1" --> "*" FnbAddon : has addons
    FnbItem "1" --> "*" RentalFnbItem : ordered in
    FnbItem "1" --> "*" FnbOrderItem : ordered as

    FnbOrder "1" --> "*" FnbOrderItem : contains

    RentalController ..> RentalService : uses
    ReservationController ..> RentalService : uses
```

---

## 4. Sequence Diagram — Mulai Rental Baru

```mermaid
sequenceDiagram
    actor Operator
    participant Browser
    participant RentalController
    participant RentalService
    participant Console
    participant Rental
    participant DB

    Operator->>Browser: Buka halaman Rentals
    Browser->>RentalController: GET /rentals
    RentalController->>DB: Query consoles (available)
    RentalController->>DB: Query employees (active)
    RentalController->>DB: Query upcoming reservations
    DB-->>RentalController: Data
    RentalController-->>Browser: Inertia: Rentals/Index

    Operator->>Browser: Isi form & klik "Mulai Rental"
    Browser->>RentalController: POST /rentals {console_id, employee_id, customer_name, duration_hours?}
    RentalController->>RentalService: create(validatedData)
    RentalService->>Console: findOrFail(console_id)
    Console-->>RentalService: Console object
    RentalService->>Rental: create({transaction_code, customer_name, status:running, ...})
    Rental-->>RentalService: Rental object
    RentalService->>Console: update({status: occupied})
    RentalService-->>RentalController: Rental object
    RentalController-->>Browser: redirect → /rentals/{id} (with success flash)
    Browser-->>Operator: Halaman Detail Rental (live monitoring)
```

---

## 5. Sequence Diagram — Selesaikan & Bayar Rental

```mermaid
sequenceDiagram
    actor Operator
    participant Browser
    participant RentalController
    participant RentalService
    participant Rental
    participant RentalPayment
    participant Console

    Operator->>Browser: Klik "Selesaikan Rental"
    Browser->>RentalController: POST /rentals/{id}/finish
    RentalController->>RentalService: finish(rental)
    RentalService->>Rental: calculate rental_amount (billing logic)
    Note over RentalService: Open Time: ceil ke 10 menit, min 60 menit<br/>Duration: billing sesuai scheduled_end_at
    RentalService->>Rental: update({status:finished, ended_at:now, rental_amount, total_amount})
    RentalService->>Console: update({status: available})
    RentalController-->>Browser: redirect → /rentals/{id}/payment

    Operator->>Browser: Pilih metode & jumlah bayar, klik "Bayar"
    Browser->>RentalController: POST /rentals/{id}/pay {payments:[{method, amount}]}
    RentalController->>RentalService: pay(rental, payments)
    loop Setiap payment
        RentalService->>RentalPayment: create({rental_id, method, amount})
    end
    RentalService->>Rental: update paid_amount, status (paid / half_paid)
    RentalController-->>Browser: redirect → /rentals/{id}/receipt (jika lunas)
    Browser-->>Operator: Struk Pembayaran
```

---

## 6. Sequence Diagram — FnB Order Standalone

```mermaid
sequenceDiagram
    actor Operator
    participant Browser
    participant FnbOrderController
    participant FnbOrder
    participant FnbOrderItem
    participant FnbItem
    participant DB

    Operator->>Browser: Buka halaman FnB Orders
    Browser->>FnbOrderController: GET /fnb-orders
    FnbOrderController->>DB: Query orders (paginated)
    FnbOrderController->>DB: Query fnb_items (available)
    FnbOrderController->>DB: Query fnb_addons (available)
    FnbOrderController->>DB: Query employees
    FnbOrderController->>DB: Query consoles
    DB-->>FnbOrderController: Data
    FnbOrderController-->>Browser: Inertia: FnbOrders/Index

    Operator->>Browser: Pilih item, qty, addon & klik "Pesan"
    Browser->>FnbOrderController: POST /fnb-orders {employee_id, items:[...], payment_method}
    FnbOrderController->>FnbOrderController: Hitung total (unit_price + addons) × qty
    FnbOrderController->>FnbOrder: create({code:FNB-XXXXXX, total_amount, status:paid, paid_at:now})
    loop Setiap item
        FnbOrderController->>FnbOrderItem: create({fnb_order_id, fnb_item_id, qty, subtotal, addons})
    end
    FnbOrderController-->>Browser: redirect → /fnb-orders (with success)
```

---

## 7. Sequence Diagram — Konversi Reservasi ke Rental

```mermaid
sequenceDiagram
    actor Operator
    participant Browser
    participant ReservationController
    participant Reservation
    participant RentalService
    participant Rental
    participant Console

    Operator->>Browser: Klik "Konversi ke Rental" pada reservasi
    Browser->>ReservationController: POST /reservations/{id}/convert
    ReservationController->>Reservation: cek status !== 'converted'
    alt sudah converted
        ReservationController-->>Browser: back() with error
    else belum converted
        ReservationController->>RentalService: create({console_id, employee_id, customer_name, duration_hours})
        RentalService->>Console: findOrFail(console_id)
        RentalService->>Rental: create({status:running, ...})
        RentalService->>Console: update({status: occupied})
        RentalService-->>ReservationController: Rental object
        ReservationController->>Reservation: update({status: converted, rental_id})
        ReservationController-->>Browser: redirect → /rentals/{id}
        Browser-->>Operator: Halaman Detail Rental (running)
    end
```

---

## 8. Activity Diagram — Proses Rental (Lengkap)

```mermaid
flowchart TD
    Start([🟢 Mulai])
    A["Operator membuka halaman Rentals"]
    B["Pilih Console yang Available"]
    C["Isi data Customer & Operator"]
    D{Ada durasi\ntetap?}
    E1["Buat Rental: open_time\n(scheduled_end_at = null)"]
    E2["Buat Rental: duration\n(scheduled_end_at = now + X jam)"]
    F["Console → status: occupied"]
    G["Rental berjalan (status: running)"]

    H{Operator\ningin...}
    H1["Tambah FnB"]
    H2["Tambah Waktu (Extension)"]
    H3["Selesaikan"]
    H4["DP / Bayar Sebagian"]

    I1["Pilih FnB Item & Addon"]
    I2["Create RentalFnbItem"]
    I3["Update fnb_amount & total_amount"]

    J1["Input tambahan menit & harga"]
    J2["Create RentalExtension"]
    J3["Update extra_amount & scheduled_end_at"]

    K{Tipe rental?}
    K1["Billing: scheduled duration\n(price_per_hour / 60 × scheduled_minutes)"]
    K2["Billing: round up ke 10 menit\n(min 60 menit)"]
    L["Update rental_amount, ended_at\nstatus → finished"]
    M["Console → status: available"]
    N["Redirect ke halaman Payment"]

    P["Input metode & jumlah bayar"]
    Q["Create RentalPayment"]
    R{Total bayar\n>= total?}
    R1["status → paid"]
    R2["status → half_paid"]
    S{ended_at\nada?}
    S1["Redirect ke Receipt ✅"]
    S2["Kembali ke Show Rental\n(masih running, ada DP)"]

    End1([🔴 Selesai — Lunas])
    End2([⏸️ Menunggu pelunasan])

    Start --> A --> B --> C --> D
    D -->|Ya| E2
    D -->|Tidak| E1
    E1 --> F
    E2 --> F
    F --> G

    G --> H
    H -->|"Tambah FnB"| H1
    H -->|"Extend"| H2
    H -->|"Finish"| H3
    H -->|"Bayar DP"| H4

    H1 --> I1 --> I2 --> I3 --> G
    H2 --> J1 --> J2 --> J3 --> G
    H4 --> P

    H3 --> K
    K -->|duration| K1 --> L
    K -->|open_time| K2 --> L
    L --> M --> N --> P

    P --> Q --> R
    R -->|Ya| R1 --> S
    R -->|Tidak| R2 --> S
    S -->|Ya| S1 --> End1
    S -->|Tidak| S2 --> End2

    End2 -.->|"Operator lunasi nanti"| N
```

---

## 9. Activity Diagram — FnB Order Standalone

```mermaid
flowchart TD
    Start([🟢 Mulai])
    A["Operator buka halaman FnB Orders"]
    B["Klik Buat Order FnB"]
    C["Pilih Operator & Console (opsional)"]
    D["Tambah item: FnbItem + qty + addon"]
    E["Pilih metode pembayaran (cash/qris)"]
    F["Submit form"]
    G["Hitung total per item:\n(unit_price + addons_price) × qty"]
    H["Generate kode: FNB-XXXXXX"]
    I["Create FnbOrder {status: paid, paid_at: now}"]
    J["Create FnbOrderItem untuk setiap item"]
    K["Redirect ke daftar FnB Orders"]
    End([🔴 Selesai])

    Start --> A --> B --> C --> D --> E --> F
    F --> G --> H --> I --> J --> K --> End
```

---

## 10. State Machine Diagram — Rental Status

```mermaid
stateDiagram-v2
    [*] --> running : create() — Rental dimulai

    running --> finished : finish() — Waktu selesai, hitung biaya
    running --> half_paid : pay() saat masih running (DP mid-session)

    finished --> paid : pay() — Bayar lunas
    finished --> half_paid : pay() — Bayar sebagian

    half_paid --> paid : pay() — Pelunasan sisa tagihan
    half_paid --> finished : ended_at not null + belum lunas

    paid --> [*] : Transaksi selesai ✅

    note right of running
        Console.status = occupied
        FnB & Extension bisa ditambah
    end note

    note right of finished
        Console.status = available
        rental_amount dihitung
    end note

    note right of paid
        Struk bisa dicetak
    end note
```

---

## 11. State Machine Diagram — Reservation Status

```mermaid
stateDiagram-v2
    [*] --> pending : store() — Reservasi dibuat

    pending --> confirmed : update(status=confirmed)
    pending --> cancelled : update(status=cancelled) / destroy()

    confirmed --> cancelled : update(status=cancelled)
    confirmed --> converted : convert() — Jadi Rental aktif

    cancelled --> [*] : Reservasi batal

    note right of converted
        Rental baru dibuat (status: running)
        reservation.rental_id = rental.id
    end note
```

---

## 12. Component Diagram

```mermaid
graph TB
    subgraph Frontend ["🖥️ Frontend (Vue + Inertia.js)"]
        FE_RENTALS["Pages/Rentals\n(Index, Show, Payment, Receipt, History)"]
        FE_FNB["Pages/FnbOrders\n(Index, Show)"]
        FE_RESERVATION["Pages/Reservations\n(Index)"]
        FE_MASTER["Pages/Master\n(Employees, Consoles, Games, FnB, Packages)"]
        FE_DASHBOARD["Pages/Dashboard\n(Index, RoomMonitor)"]
    end

    subgraph Backend ["⚙️ Backend (Laravel)"]
        subgraph Controllers ["Controllers"]
            RC["RentalController"]
            FOC["FnbOrderController"]
            RSC["ReservationController"]
            CC["ConsoleController"]
            EC["EmployeeController"]
            DC["DashboardController"]
        end

        subgraph Services ["Services"]
            RS["RentalService\n(create, addTime, addFnb, finish, pay)"]
            DS["DashboardService"]
        end

        subgraph Models ["Eloquent Models"]
            M_RENTAL["Rental"]
            M_CONSOLE["Console"]
            M_EMPLOYEE["Employee"]
            M_FNB["FnbItem / FnbAddon"]
            M_ORDER["FnbOrder / FnbOrderItem"]
            M_RES["Reservation"]
            M_PAY["RentalPayment"]
            M_EXT["RentalExtension"]
        end
    end

    subgraph DB ["🗄️ Database (PostgreSQL)"]
        T_RENTALS[("rentals")]
        T_CONSOLES[("consoles")]
        T_EMPLOYEES[("employees")]
        T_FNB[("fnb_items / fnb_addons")]
        T_ORDERS[("fnb_orders / fnb_order_items")]
        T_RES[("reservations")]
        T_PAY[("rental_payments")]
        T_EXT[("rental_extensions")]
    end

    FE_RENTALS <--> RC
    FE_FNB <--> FOC
    FE_RESERVATION <--> RSC
    FE_MASTER <--> CC
    FE_MASTER <--> EC
    FE_DASHBOARD <--> DC

    RC --> RS
    RSC --> RS
    DC --> DS

    RS --> M_RENTAL
    RS --> M_CONSOLE
    RS --> M_EXT
    RS --> M_PAY
    FOC --> M_ORDER
    RSC --> M_RES

    M_RENTAL --- T_RENTALS
    M_CONSOLE --- T_CONSOLES
    M_EMPLOYEE --- T_EMPLOYEES
    M_FNB --- T_FNB
    M_ORDER --- T_ORDERS
    M_RES --- T_RES
    M_PAY --- T_PAY
    M_EXT --- T_EXT
```

---

## Ringkasan Sistem

| Entitas | Tabel | Model | Relasi Utama |
|---|---|---|---|
| User | `users` | `User` | - |
| Karyawan | `employees` | `Employee` | hasMany: Rental, Reservation, FnbOrder, CashOutbound |
| Console | `consoles` | `Console` | belongsToMany: Game; hasMany: Rental, Reservation, FnbOrder |
| Game | `games` | `Game` | belongsToMany: Console |
| Paket | `packages` | `Package` | hasMany: Rental |
| Rental | `rentals` | `Rental` | belongsTo: Console, Employee, Package; hasMany: Extension, FnbItem, Payment |
| Perpanjangan | `rental_extensions` | `RentalExtension` | belongsTo: Rental |
| Pembayaran | `rental_payments` | `RentalPayment` | belongsTo: Rental |
| Item FnB | `fnb_items` | `FnbItem` | hasMany: FnbAddon, RentalFnbItem, FnbOrderItem |
| Addon FnB | `fnb_addons` | `FnbAddon` | belongsTo: FnbItem |
| FnB di Rental | `rental_fnb_items` | `RentalFnbItem` | belongsTo: Rental, FnbItem |
| Order FnB | `fnb_orders` | `FnbOrder` | belongsTo: Employee, Console; hasMany: FnbOrderItem |
| Item Order FnB | `fnb_order_items` | `FnbOrderItem` | belongsTo: FnbOrder, FnbItem |
| Reservasi | `reservations` | `Reservation` | belongsTo: Console, Employee, Rental |
| Pengeluaran Kas | `cash_outbounds` | `CashOutbound` | belongsTo: Employee |
