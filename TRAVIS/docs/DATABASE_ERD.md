# TRAVIS Database Entity–Relationship Diagram

This ERD represents the latest intended TRAVIS schema based on
`database/travis.sql` and the migrations in `database/`. It covers the user,
traffic monitoring, enforcement, payment, analytics, reporting, public portal,
and hybrid edge-worker domains.

## Complete logical ERD

```mermaid
erDiagram
    USERS {
        BIGINT user_id PK
        VARCHAR full_name
        VARCHAR email UK
        VARCHAR password
        ENUM role
        ENUM status
        DATETIME created_at
        DATETIME updated_at
    }

    CAMERAS {
        BIGINT camera_id PK
        VARCHAR camera_name
        VARCHAR location
        VARCHAR ip_address UK
        VARCHAR camera_username
        TEXT camera_password_encrypted
        ENUM stream_quality
        VARCHAR calibration_profile
        VARCHAR edge_worker_id
        BOOLEAN is_enabled
        ENUM status
        DATE installed_at
        DATETIME last_maintenance_at
        DATETIME created_at
        DATETIME updated_at
    }

    CAMERA_MONITORING_LOGS {
        BIGINT log_id PK
        BIGINT camera_id FK
        DATETIME recorded_at
        INT vehicle_count
        INT inbound_count
        INT outbound_count
        ENUM congestion_level
        ENUM officer_presence
        ENUM potential_collision
        TEXT incident_notes
        BOOLEAN alert_generated
        DATETIME created_at
    }

    MONITORING_ALERTS {
        BIGINT alert_id PK
        BIGINT camera_log_id FK
        ENUM alert_type
        ENUM severity
        TEXT message
        ENUM status
        DATETIME generated_at
        BIGINT acknowledged_by FK
        DATETIME acknowledged_at
    }

    OFFICER_ZONES {
        BIGINT zone_id PK
        VARCHAR zone_name
        VARCHAR description
        VARCHAR location_details
    }

    OFFICERS {
        BIGINT officer_id PK
        VARCHAR first_name
        VARCHAR middle_name
        VARCHAR last_name
        VARCHAR suffix
        VARCHAR badge_number UK
        VARCHAR rank
        VARCHAR contact_number
        VARCHAR email UK
        VARCHAR address
        DATE birth_date
        DATE hire_date
        VARCHAR emergency_contact_name
        VARCHAR emergency_contact_number
        BIGINT zone_id FK
        ENUM status
        DATETIME created_at
        DATETIME updated_at
    }

    OFFICER_DUTY_SCHEDULES {
        BIGINT schedule_id PK
        BIGINT officer_id FK
        DATE duty_date
        TIME shift_start
        TIME shift_end
        ENUM status
        VARCHAR notes
        DATETIME created_at
    }

    OFFICER_PRESENCE_LOGS {
        BIGINT presence_id PK
        BIGINT officer_id FK
        BIGINT zone_id FK
        DATETIME presence_date
        ENUM status
        BIGINT recorded_by FK
        VARCHAR remarks
    }

    VIOLATIONS {
        BIGINT violation_id PK
        VARCHAR ticket_number UK
        VARCHAR driver_name
        VARCHAR driver_last_name
        VARCHAR driver_first_name
        VARCHAR driver_middle_name
        VARCHAR driver_suffix
        VARCHAR driver_address
        DATE date_of_birth
        VARCHAR license_number
        DATE license_expiry_date
        BOOLEAN has_no_license
        BOOLEAN license_confiscated
        VARCHAR license_remarks
        VARCHAR plate_number
        BOOLEAN has_no_plate
        VARCHAR vehicle_owner
        VARCHAR vehicle_registration_number
        ENUM vehicle_type
        VARCHAR vehicle_color
        VARCHAR insurance_policy_number
        VARCHAR coding_sticker_number
        VARCHAR vehicle_toda
        VARCHAR violation_type
        VARCHAR violation_location
        DATE violation_date
        TIME violation_time
        INT offense_number
        TEXT ticket_remarks
        VARCHAR apprehending_officer_name
        VARCHAR apprehending_officer_position
        DATETIME apprehension_datetime
        DECIMAL penalty_amount
        ENUM input_method
        BIGINT encoded_by FK
        ENUM status
        DATETIME created_at
        DATETIME updated_at
    }

    VIOLATION_ITEMS {
        BIGINT violation_item_id PK
        BIGINT violation_id FK
        VARCHAR violation_type
        DECIMAL penalty_amount
        DECIMAL ocr_confidence
        DATETIME created_at
    }

    PAYMENTS {
        BIGINT payment_id PK
        BIGINT violation_id FK, UK
        DECIMAL amount_paid
        ENUM payment_status
        DATETIME payment_date
        BIGINT received_by FK
        ENUM payment_method
        VARCHAR receipt_reference
        VARCHAR official_receipt_number UK
        DATETIME created_at
    }

    AUDIT_LOGS {
        BIGINT audit_id PK
        BIGINT user_id FK
        VARCHAR actor_name
        VARCHAR actor_role
        VARCHAR action
        VARCHAR module
        VARCHAR entity_type
        VARCHAR entity_id
        VARCHAR description
        ENUM outcome
        VARCHAR ip_address
        VARCHAR user_agent
        DATETIME created_at
    }

    REPORTS {
        BIGINT report_id PK
        ENUM report_type
        VARCHAR title
        TEXT description
        BIGINT generated_by FK
        DATETIME generated_at
        DATE period_start
        DATE period_end
        ENUM status
        VARCHAR file_path
    }

    PUBLIC_ANNOUNCEMENTS {
        BIGINT announcement_id PK
        VARCHAR title
        TEXT content
        ENUM announcement_type
        VARCHAR image_path
        DATETIME publish_date
        DATETIME expiry_date
        ENUM status
        BIGINT created_by FK
        DATETIME created_at
    }

    PUBLIC_CONTACTS {
        INT contact_id PK
        VARCHAR office_name
        VARCHAR phone_number
        VARCHAR description
        VARCHAR office_hours
        ENUM category
        INT display_order
        BOOLEAN is_active
        DATETIME created_at
        DATETIME updated_at
    }

    SYSTEM_SETTINGS {
        VARCHAR setting_key PK
        TEXT setting_value
        DATETIME updated_at
    }

    ML_PREDICTIONS {
        BIGINT prediction_id PK
        ENUM prediction_type
        VARCHAR predicted_result
        DECIMAL confidence_score
        VARCHAR location
        VARCHAR violation_type
        INT frequency_count
        ENUM risk_level
        DATETIME prediction_date
        TEXT notes
    }

    VIOLATION_HOTSPOTS {
        BIGINT hotspot_id PK
        VARCHAR cluster_label
        VARCHAR location
        VARCHAR violation_type
        INT frequency_count
        ENUM risk_level
        ENUM model_type
        DATETIME generated_at
        TEXT additional_info
    }

    HYBRID_JOBS {
        BIGINT job_id PK
        VARCHAR job_type
        LONGTEXT payload_json
        ENUM status
        LONGTEXT result_json
        VARCHAR error_message
        BIGINT requested_by
        VARCHAR worker_id
        DATETIME created_at
        DATETIME claimed_at
        DATETIME completed_at
        DATETIME updated_at
    }

    USERS ||--o{ VIOLATIONS : encodes
    USERS ||--o{ PAYMENTS : receives
    USERS ||--o{ AUDIT_LOGS : performs
    USERS ||--o{ MONITORING_ALERTS : acknowledges
    USERS ||--o{ OFFICER_PRESENCE_LOGS : records
    USERS ||--o{ REPORTS : generates
    USERS ||--o{ PUBLIC_ANNOUNCEMENTS : creates

    CAMERAS ||--o{ CAMERA_MONITORING_LOGS : produces
    CAMERA_MONITORING_LOGS o|--o{ MONITORING_ALERTS : triggers

    OFFICER_ZONES o|--o{ OFFICERS : assigns
    OFFICER_ZONES o|--o{ OFFICER_PRESENCE_LOGS : locates
    OFFICERS ||--o{ OFFICER_DUTY_SCHEDULES : has
    OFFICERS ||--o{ OFFICER_PRESENCE_LOGS : has

    VIOLATIONS ||--o{ VIOLATION_ITEMS : contains
    VIOLATIONS ||--o| PAYMENTS : settles
```

## Relationship summary

| Parent | Child | Cardinality | Foreign key | Delete behavior |
|---|---|---:|---|---|
| `users` | `violations` | 1 to many | `violations.encoded_by` | Set null |
| `users` | `payments` | 1 to many | `payments.received_by` | Set null |
| `users` | `audit_logs` | 1 to many | `audit_logs.user_id` | Set null |
| `users` | `monitoring_alerts` | 1 to many | `monitoring_alerts.acknowledged_by` | Set null |
| `users` | `officer_presence_logs` | 1 to many | `officer_presence_logs.recorded_by` | Set null |
| `users` | `reports` | 1 to many | `reports.generated_by` | Set null |
| `users` | `public_announcements` | 1 to many | `public_announcements.created_by` | Set null |
| `cameras` | `camera_monitoring_logs` | 1 to many | `camera_monitoring_logs.camera_id` | Cascade |
| `camera_monitoring_logs` | `monitoring_alerts` | 1 to many, optional parent | `monitoring_alerts.camera_log_id` | Set null |
| `officer_zones` | `officers` | 1 to many, optional zone | `officers.zone_id` | Set null |
| `officer_zones` | `officer_presence_logs` | 1 to many, optional zone | `officer_presence_logs.zone_id` | Set null |
| `officers` | `officer_duty_schedules` | 1 to many | `officer_duty_schedules.officer_id` | Cascade |
| `officers` | `officer_presence_logs` | 1 to many | `officer_presence_logs.officer_id` | Cascade |
| `violations` | `violation_items` | 1 to many | `violation_items.violation_id` | Cascade |
| `violations` | `payments` | 1 to zero-or-one | `payments.violation_id` | Cascade |

## Important modeling notes

1. `payments.violation_id` is unique, so one violation can have at most one
   payment record.
2. `violation_items` supports multiple offenses under one physical ticket.
   The combination of `violation_id` and `violation_type` is unique.
3. The apprehending officer is currently copied into
   `violations.apprehending_officer_name` and
   `violations.apprehending_officer_position`. There is no `officer_id` foreign
   key, so the violation keeps a historical text snapshot even if an officer
   record changes.
4. `hybrid_jobs.requested_by` contains a user ID, but the current table does
   not define a foreign-key constraint to `users`.
5. `cameras.edge_worker_id` identifies the assigned edge laptop as text. There
   is currently no separate `edge_workers` table.
6. `ml_predictions`, `violation_hotspots`, `public_contacts`,
   `system_settings`, and `hybrid_jobs` are standalone entities in the current
   physical schema.
7. Camera passwords are stored encrypted using AES-256-GCM in
   `cameras.camera_password_encrypted`; they must never be shown in an ERD as
   plain-text credentials.

## Recommended normalization additions

These are improvements, not existing foreign keys:

- Add `violations.apprehending_officer_id` referencing `officers.officer_id`,
  while retaining the current name and position fields as historical snapshot
  values.
- Add an `edge_workers` table and reference it from `cameras.edge_worker_id`
  and `hybrid_jobs.worker_id`.
- Add a foreign key from `hybrid_jobs.requested_by` to `users.user_id` with
  `ON DELETE SET NULL`.
- Replace runtime table alterations with versioned database migrations so the
  local, VPS, and documentation schemas cannot drift.
