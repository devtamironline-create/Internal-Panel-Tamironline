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
- Framework: Laravel (PHP)
- Modules: Modular structure under `/Modules/`
- Key modules: Attendance, Salary, CRM, Users
- Database: MySQL with Jalali (Shamsi) calendar dates
- Frontend: Blade templates + Livewire

## Commit Convention
- One fix = one commit
- Commit message format: `fix(module): short description`
- Example: `fix(salary): protect against division by zero in SalaryCalculator`

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
