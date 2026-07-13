# TRAVIS Treasurer Dashboard

Pure **HTML / CSS / vanilla JS** front-end with **native PHP + MySQL** REST endpoints.
Part of the TRAVIS (Traffic Violation Recognition and AI Surveillance) System.

## Preview
Open `index.html` in a browser, or serve the folder with any web server:
```
php -S localhost:8000 -t public/travis
```
Then visit http://localhost:8000

## Structure
```
public/travis/
├── index.html         # Single-file shell with sidebar + all pages
├── styles.css         # Design system (Navy / Teal / White / Gray + glassmorphism)
├── app.js             # Page rendering, charts (Chart.js CDN), modals, toasts
└── api/               # PHP REST stubs — connect to MySQL
    ├── db.php         # PDO connection helper
    ├── stats.php      # GET dashboard summary
    ├── violations.php # GET traffic violation records
    └── payments.php   # GET/POST payment records
```

## Pages Included
- Dashboard (stat cards, recent + pending payment tables, 3 charts)
- Traffic Violation Records (search / filters / actions)
- Violation Details (modal with vehicle + AI capture placeholders)
- Payment Management (form + cashier quick-info card)
- Collection Reports (daily/weekly/monthly/annual + PDF/Excel/Print)
- Payment History (search, date range, print receipt)
- Notifications (grid of colored alert cards)
- Profile (treasurer info + edit / change password)

## Role Restrictions
The Treasurer role **cannot** access AI Monitoring, Live/Tapo Cameras,
Uploaded Videos, ML Predictions, User Management, or System Settings.
Enforce this on the backend (session role check) in addition to the UI.

## Suggested MySQL Schema
```sql
CREATE TABLE violations (
  id VARCHAR(32) PRIMARY KEY,
  plate_number VARCHAR(16),
  vehicle_type VARCHAR(32),
  violation_type VARCHAR(64),
  location VARCHAR(128),
  violation_datetime DATETIME,
  fine_amount DECIMAL(10,2),
  payment_status ENUM('Pending','Paid','Overdue') DEFAULT 'Pending',
  vehicle_image VARCHAR(255),
  ai_capture_image VARCHAR(255)
);

CREATE TABLE payments (
  receipt_no VARCHAR(32) PRIMARY KEY,
  violation_id VARCHAR(32),
  plate_number VARCHAR(16),
  amount DECIMAL(10,2),
  payment_date DATE,
  payment_method VARCHAR(32),
  notes TEXT,
  processed_by VARCHAR(64),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (violation_id) REFERENCES violations(id)
);
```

## Wiring the UI to PHP
Replace the `DATA` object literal in `app.js` with `fetch()` calls, e.g.:
```js
const res = await fetch('api/stats.php').then(r => r.json());
DATA.stats = res.data;
```
