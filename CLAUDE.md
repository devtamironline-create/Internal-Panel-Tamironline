# Project Rules / قوانین پروژه

## Language
- This is a Persian (Farsi) Laravel project - all user-facing messages must be in Persian
- Code, variable names, and comments follow Laravel conventions (English)

## Development Rules
- Before making any change, list exactly what will be modified and wait for user confirmation
- Each fix/change must be in a separate commit with a clear message
- Do NOT touch files outside the scope of the current request
- Run tests after each change if tests exist
- Do NOT add features, refactor, or "improve" code beyond what was explicitly asked

## Architecture
- **Framework:** Laravel 12 (PHP ^8.2)
- **Modules:** Modular structure under `/Modules/` using `nwidart/laravel-modules`
- **Database:** MySQL with Jalali (Shamsi) calendar dates (`morilog/jalali`)
- **Frontend:** Blade templates + Livewire 3
- **Auth/Permissions:** Spatie Laravel-Permission (roles & permissions)
- **WebSocket:** Laravel Reverb for real-time features
- **SMS:** Kavenegar SMS API
- **PDF:** mPDF
- **Excel:** PhpSpreadsheet
- **Timezone:** Asia/Tehran
- **Locale:** fa (Persian)

## Modules
| Module | Description |
|--------|-------------|
| **Warehouse** | Order management, shipping providers, WooCommerce sync, packing, dispatch, printing |
| **Attendance** | Employee attendance tracking with IP validation |
| **Salary** | Salary calculation with overtime, holidays, Jalali dates |
| **Staff** | Staff/employee management |
| **Technician** | Technician request management with approve/reject/delete permissions |
| **CRM** | Customer relationship management |
| **OKR** | Objectives and key results |
| **Task** | Task management (integrated with chat) |
| **SMS** | SMS service (Kavenegar) |
| **Core** | Shared core functionality |

## Warehouse Module (Main Module)

### Order Status Flow
```
pending → packed (scan queue) → shipped → delivered
              ↕
         supply_wait (temporary - preserves original shipping type)
```

### Shipping Types
Defined in `WarehouseShippingType` model:
- `post` — پست (postal)
- `courier` — پیک
- `pickup` — تحویل حضوری
- `urgent` — پست فوری
- `emergency` — اورژانسی
- `courier_5day` — پیک ۵ روزه

### Shipping Providers
All providers share the same pattern: Service + Controller + View + Routes

| Provider | Service | Auth Method | Status |
|----------|---------|-------------|--------|
| **Amadest** | `AmadestService.php` | Token-based | Active |
| **Tapin** | `TapinService.php` | API Key + Shop ID | Active |
| **Postex** | `PostexService.php` | API Key (x-api-key header) | Active |
| **COD24** | `Cod24Service.php` | Username/Password → Bearer Token | Active |

Active provider is stored in `warehouse_settings` table: key = `shipping_provider`, value = `amadest|tapin|postex|cod24`

### Provider Integration Points
- **PrintController::invoice()** — Auto-registers orders with the active shipping provider when viewing invoice
- **PrintController::retryRegister()** — Manual retry for failed registrations
- Provider selection UI exists on each provider's settings page (buttons for all 4 providers)
- Sidebar navigation: اتصال آمادست / اتصال تاپین / اتصال پستکس / اتصال COD24

### COD24 API Reference
- **Base URL:** `https://api.cod24.ir`
- **Auth:** `POST /api/Account/getToken` with `{userName, password}` → Bearer token (cached 23h)
- **Create Order:** `POST /api/Order/addOrder`
- **Price:** `POST /api/Order/getPostPrice`
- **Cities:** `POST /api/City/getPostCities`, `POST /api/City/getCities`
- **States:** `POST /api/State/getStates`
- **Tracking:** `GET /tracking/{barcode}`, `POST /api/Order/getBarcodeStatus`
- **Cancel:** `POST /api/Order/cancelOrder`
- **Wallet:** `POST /api/Wallet/getWalletAmount`

### Key Warehouse Models
| Model | Purpose |
|-------|---------|
| `WarehouseOrder` | Main order model (status, shipping_type, barcode, tracking) |
| `WarehouseOrderItem` | Order line items (product, quantity, weight, dimensions) |
| `WarehouseProduct` | Product catalog synced from WooCommerce |
| `WarehouseSetting` | Key-value settings store for all provider configs |
| `WarehouseShippingType` | Shipping type definitions (slug, timer, priority) |
| `WarehouseBoxSize` | Box size options for packing |
| `OrderLog` | Order action audit trail |
| `OrderAssignment` | Staff order assignment for distribution |
| `ReprintRequest` | Reprint approval workflow |

### Settings Keys Pattern
Each provider stores settings with prefix:
- Amadest: `amadest_*`
- Tapin: `tapin_*`
- Postex: `postex_*`
- COD24: `cod24_api_url`, `cod24_username`, `cod24_password`, `cod24_token`, `cod24_sender_name`, `cod24_sender_mobile`, `cod24_id_type_send`, `cod24_id_pay_method`, `cod24_id_packet_type`, `cod24_fallback_city_code`, `cod24_city_map`

### WooCommerce Integration
- Orders synced from WooCommerce via `WooCommerceController`
- WC order data stored in `wc_order_data` JSON field on `WarehouseOrder`
- Shipping/billing address extracted from `wc_order_data.shipping` and `wc_order_data.billing`
- Provider registration flags stored in `wc_order_data.{provider}.registered`

### Order Distribution
- Strategies: `round_robin`, `least_orders`, `shipping_type`
- Shipping type mapping: specific shipping types → specific operators
- Config stored in `distribution_shipping_map` setting

## Key Files Reference

### Warehouse Routes
`Modules/Warehouse/Routes/web.php` — All warehouse routes grouped under `/warehouse` prefix

### Controllers
- `WarehouseController` — Main CRUD, status updates, supply_wait
- `PrintController` — Invoice printing, auto-registration with shipping providers
- `DispatchController` — Scan station, shipping dispatch
- `PackingController` — Packing station with barcode scanning
- `AmadestController` / `TapinController` / `PostexController` / `Cod24Controller` — Provider settings
- `WooCommerceController` — WC sync, product management
- `SettingsController` — General warehouse settings
- `StaffDistributionController` — Order distribution to staff

### Services
- `Modules/Warehouse/Services/AmadestService.php`
- `Modules/Warehouse/Services/TapinService.php`
- `Modules/Warehouse/Services/PostexService.php`
- `Modules/Warehouse/Services/Cod24Service.php`
- `Modules/Warehouse/Services/OrderDistributionService.php`

### Views
- `Modules/Warehouse/Resources/views/warehouse/` — Order management views
- `Modules/Warehouse/Resources/views/print/` — Invoice/label templates
- `Modules/Warehouse/Resources/views/amadest/` — Amadest settings
- `Modules/Warehouse/Resources/views/tapin/` — Tapin settings
- `Modules/Warehouse/Resources/views/postex/` — Postex settings
- `Modules/Warehouse/Resources/views/cod24/` — COD24 settings
- `resources/views/layouts/admin.blade.php` — Main admin layout with sidebar

## Permissions
Key permissions managed by Spatie:
- `manage-warehouse` — Full warehouse access
- `manage-permissions` — Permission management (acts as super-admin)
- `manage-technicians` — Full technician module access
- `approve-technician` — Approve/reject technician requests
- `delete-technician` — Delete technician requests

## Commit Convention
- One fix = one commit
- Commit message format: `fix(module): short description` or `feat(module): short description`
- Example: `fix(salary): protect against division by zero in SalaryCalculator`
- Example: `feat(warehouse): add COD24 shipping provider integration`

## Current Priority Fixes (ordered)
1. Fix string time comparison in AttendanceController (lines 122-124, 166-169)
2. Fix holiday/regular overtime separation in SalaryCalculator (lines 272-275)
3. Fix cross-month leave calculation in SalaryCalculator (lines 296-302)
4. Add division by zero protection in SalaryCalculator (lines 78-79)
5. Fix hourly leave validation in LeaveController (line 161)
6. Fix extra lunch minutes not being saved in AttendanceController (line 238)
7. Fix allowed_ips null check in AttendanceController (line 92)
8. Fix LeaveRequest hourly-to-daily conversion using hardcoded 8 instead of settings
9. Fix markAttendanceAsLeave not checking weekends/holidays
10. Fix calculateCurrent inconsistency with main calculate method in SalaryCalculator

## Tests
- PHPUnit ^11.0 is configured but **no tests exist yet**
- Laravel Pint is available for code formatting
