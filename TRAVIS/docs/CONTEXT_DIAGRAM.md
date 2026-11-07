# TRAVIS System Context Diagram

This context diagram represents the entire TRAVIS system as one process and
shows its interactions with external users and devices. Internal processes,
database tables, and data stores are intentionally excluded because they
belong in Diagram 0 and the lower-level DFDs.

## Context Diagram

```mermaid
flowchart LR
    ADMIN["Administrator /<br/>Authorized TMO Personnel"]
    TREASURY["Treasury Personnel"]

    TRAVIS(["0<br/><b>Manage Traffic Monitoring, Enforcement,<br/>Payments, Analytics, and Public Information</b><br/><br/>TRAVIS: An AI, Computer Vision, and IoT-Based<br/>Traffic Monitoring and Decision Support System<br/>for the Traffic Management Office of<br/>Nasugbu, Batangas"])

    CAMERA["IoT-Enabled<br/>Camera Device"]
    PUBLIC["Public User /<br/>Community"]

    ADMIN -->|"Account Credentials"| TRAVIS
    ADMIN -->|"Monitoring, Analytics, and Report Requests"| TRAVIS
    ADMIN -->|"Violation Ticket and OCR Details"| TRAVIS
    ADMIN -->|"Enforcer, Schedule, Camera, and System Details"| TRAVIS
    ADMIN -->|"Announcement and Advisory Details"| TRAVIS

    TRAVIS -->|"Authentication and Access Status"| ADMIN
    TRAVIS -->|"Live Monitoring, Camera, and Alert Status"| ADMIN
    TRAVIS -->|"Violation Record Confirmation"| ADMIN
    TRAVIS -->|"Dashboard Analytics and Decision-Support Results"| ADMIN
    TRAVIS -->|"Generated Reports and Management Records"| ADMIN

    TREASURY -->|"Account Credentials"| TRAVIS
    TREASURY -->|"Payment Information and Processing Request"| TRAVIS
    TREASURY -->|"Collection Report Request"| TRAVIS

    TRAVIS -->|"Authentication and Access Status"| TREASURY
    TRAVIS -->|"Violation Details and Amount Due"| TREASURY
    TRAVIS -->|"Payment Confirmation and Official Receipt"| TREASURY
    TRAVIS -->|"Payment Status and Collection Reports"| TREASURY

    CAMERA -->|"Live Video Feed"| TRAVIS
    CAMERA -->|"Device and Stream Status"| TRAVIS
    TRAVIS -->|"Camera and Stream Configuration"| CAMERA
    TRAVIS -->|"Camera and Stream Commands"| CAMERA

    PUBLIC -->|"Public Information Request"| TRAVIS
    TRAVIS -->|"Traffic Advisories and Announcements"| PUBLIC
    TRAVIS -->|"Public Traffic Status and Contact Information"| PUBLIC

    classDef entity fill:#ffffff,stroke:#173d46,stroke-width:2px,color:#10252c;
    classDef system fill:#e8f6f3,stroke:#0b897e,stroke-width:3px,color:#10252c;
    class ADMIN,TREASURY,CAMERA,PUBLIC entity;
    class TRAVIS system;
```

## External entities and responsibilities

| External entity | Information provided to TRAVIS | Information received from TRAVIS |
|---|---|---|
| Administrator / Authorized TMO Personnel | Credentials, monitoring and analytics requests, violation and OCR details, enforcer and schedule details, camera configuration, system settings, report requests, and public announcements | Access status, monitoring and camera status, alerts, violation confirmation, analytics, predictions, management records, and generated reports |
| Treasury Personnel | Credentials, payment information, payment processing request, and collection report request | Access status, violation balance, payment confirmation, official receipt, payment status, and collection reports |
| IoT-Enabled Camera Device | Live video feed and device or stream status | Camera configuration and stream commands |
| Public User / Community | Public information request | Traffic advisories, announcements, public traffic status, and office contact information |

## Data-flow grammar check

All arrow labels are nouns or noun phrases because they describe information
being transferred. Examples include:

- `Live Video Feed`
- `Violation Ticket and OCR Details`
- `Payment Information and Processing Request`
- `Dashboard Analytics and Decision-Support Results`
- `Traffic Advisories and Announcements`

The central process uses verbs because it describes what the system does:

> **Manage Traffic Monitoring, Enforcement, Payments, Analytics, and Public
> Information**

## Alignment with the implemented system

1. Monitoring covers vehicle counting, inbound and outbound counting,
   congestion assessment, officer-presence detection, collision detection,
   live camera viewing, and alert generation.
2. Violations are entered manually or through OCR-assisted ticket scanning;
   the camera does not automatically determine ticketable violations.
3. Treasury personnel validate payments, record collections, issue official
   receipts, and access payment reports.
4. Decision support uses monitoring, violation, and payment records to present
   trends, predictions, risk information, and hotspot results.
5. Authorized TMO personnel manage traffic enforcers, duty assignments,
   cameras, system users, reports, settings, and public announcements.
6. The public website provides announcements, traffic advisories, public
   traffic status, and contact information to the community.

## Context-diagram rules applied

- Only one system process is shown and numbered `0`.
- No database or data-store symbol is included.
- No internal module is represented as an external entity.
- Every data flow connects an external entity and the TRAVIS system.
- Every process label is action-oriented, while every arrow label identifies
  the data being transferred.

