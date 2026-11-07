# TRAVIS Data Flow Diagram — Diagram 0

This proposed Diagram 0 is based on the current TRAVIS system and is limited
to five major processes.

## Revised Diagram 0

```mermaid
flowchart LR
    ADMIN["Administrator /<br/>TMO Personnel"]
    TREASURY["Treasury Personnel"]
    CAMERA["IoT-Enabled<br/>Camera Device"]

    P1(["1.0<br/>Capture and Analyze<br/>Traffic Monitoring Data"])
    P2(["2.0<br/>Encode and Manage<br/>Traffic Violation Records"])
    P3(["3.0<br/>Analyze Traffic Data and Generate<br/>Decision-Support Results"])
    P4(["4.0<br/>Validate and Process<br/>Violation Payments"])
    P5(["5.0<br/>Configure and Monitor<br/>Camera Devices"])

    D1[("D1<br/>Camera Registry and Configurations")]
    D2[("D2<br/>Traffic Monitoring Logs")]
    D3[("D3<br/>Traffic Alert Records")]
    D4[("D4<br/>Violation Records")]
    D5[("D5<br/>Payment Records")]
    D6[("D6<br/>Analytics and Report Records")]

    ADMIN -->|Traffic Monitoring Request| P1
    CAMERA -->|Live Video Feed| P1
    P5 -->|Active Camera and Stream Configuration| P1
    P1 -->|Vehicle Counts and Monitoring Results| D2
    P1 -->|Generated Alert Record| D3
    P1 -->|Live Monitoring Status| ADMIN
    P1 -->|Traffic Alert Notification| ADMIN

    ADMIN -->|Violation Ticket and OCR Details| P2
    D4 -->|Existing Violation History| P2
    P2 -->|Validated Violation Record| D4
    P2 -->|Violation Encoding Confirmation| ADMIN

    ADMIN -->|Analytics and Report Request| P3
    D2 -->|Historical Monitoring Records| P3
    D3 -->|Traffic Alert Records| P3
    D4 -->|Violation Records| P3
    D5 -->|Payment and Collection Records| P3
    P3 -->|Analytics Prediction and Report Results| D6
    P3 -->|Dashboard Analytics and Generated Reports| ADMIN

    TREASURY -->|Payment Information| P4
    D4 -->|Violation Details and Amount Due| P4
    P4 -->|Completed Payment Record| D5
    P4 -->|Updated Violation Payment Status| D4
    P4 -->|Payment Confirmation and Official Receipt| TREASURY

    ADMIN -->|Camera Registration and Configuration| P5
    CAMERA -->|Device and Stream Status| P5
    D1 -->|Saved Camera Configuration| P5
    P5 -->|Camera Configuration and Device Status| D1
    P5 -->|Camera and Stream Commands| CAMERA
    P5 -->|Camera Availability Status| ADMIN

    classDef entity fill:#ffffff,stroke:#173d46,stroke-width:2px,color:#10252c;
    classDef process fill:#e8f6f3,stroke:#0b897e,stroke-width:2px,color:#10252c;
    classDef store fill:#fff8e8,stroke:#d4931d,stroke-width:2px,color:#10252c;
    class ADMIN,TREASURY,CAMERA entity;
    class P1,P2,P3,P4,P5 process;
    class D1,D2,D3,D4,D5,D6 store;
```

## Label classification

### Process boxes: verb–object phrases

Each process describes an action performed by the system.

| Process | Verb or action | Object |
|---|---|---|
| Capture and Analyze Traffic Monitoring Data | Capture, Analyze | Traffic Monitoring Data |
| Encode and Manage Traffic Violation Records | Encode, Manage | Traffic Violation Records |
| Analyze Traffic Data and Generate Decision-Support Results | Analyze, Generate | Traffic Data, Decision-Support Results |
| Validate and Process Violation Payments | Validate, Process | Violation Payments |
| Configure and Monitor Camera Devices | Configure, Monitor | Camera Devices |

### Data-flow arrows: noun phrases

The arrow labels describe the data being transferred. They do not describe an
action or command performed by the process.

| Data-flow label | Classification |
|---|---|
| Traffic Monitoring Request | Noun phrase |
| Live Video Feed | Noun phrase |
| Active Camera and Stream Configuration | Noun phrase |
| Vehicle Counts and Monitoring Results | Noun phrase |
| Generated Alert Record | Noun phrase |
| Live Monitoring Status | Noun phrase |
| Traffic Alert Notification | Noun phrase |
| Violation Ticket and OCR Details | Noun phrase |
| Existing Violation History | Noun phrase |
| Validated Violation Record | Noun phrase |
| Violation Encoding Confirmation | Noun phrase |
| Analytics and Report Request | Noun phrase |
| Historical Monitoring Records | Noun phrase |
| Payment and Collection Records | Noun phrase |
| Analytics Prediction and Report Results | Noun phrase |
| Dashboard Analytics and Generated Reports | Noun phrase |
| Payment Information | Noun phrase |
| Violation Details and Amount Due | Noun phrase |
| Completed Payment Record | Noun phrase |
| Updated Violation Payment Status | Noun phrase |
| Payment Confirmation and Official Receipt | Noun phrase |
| Camera Registration and Configuration | Noun phrase |
| Device and Stream Status | Noun phrase |
| Saved Camera Configuration | Noun phrase |
| Camera Configuration and Device Status | Noun phrase |
| Camera and Stream Commands | Noun phrase |
| Camera Availability Status | Noun phrase |

## External entities

| External entity | Role in the DFD |
|---|---|
| Administrator / TMO Personnel | Requests monitoring and analytics, encodes violations, manages cameras, and receives system results. |
| Treasury Personnel | Provides payment information and receives payment confirmation and the official receipt. |
| IoT-Enabled Camera Device | Supplies live video and device status and receives camera or stream configuration commands. |

## Data stores

| ID | Data store | Current database basis |
|---|---|---|
| D1 | Camera Registry and Configurations | `cameras` and calibration configurations |
| D2 | Traffic Monitoring Logs | `camera_monitoring_logs` |
| D3 | Traffic Alert Records | `monitoring_alerts` |
| D4 | Violation Records | `violations` and `violation_items` |
| D5 | Payment Records | `payments` |
| D6 | Analytics and Report Records | `ml_predictions`, `violation_hotspots`, and `reports` |

## Alignment notes

1. Traffic violation detection was replaced with violation encoding and
   management. TRAVIS does not automatically determine ticketable traffic
   violations from the CCTV feed; violations are entered manually or with
   OCR-assisted ticket scanning.
2. The monitoring process covers vehicle counting, inbound and outbound
   counting, congestion assessment, officer-presence detection, collision
   detection, and alert generation.
3. Report generation is included in Process 3.0 to keep Diagram 0 within the
   required maximum of five processes.
4. Camera configuration is kept separate from traffic analysis. Process 5.0
   manages devices and stream settings, while Process 1.0 processes the live
   feed and stores monitoring results.
5. Every external-entity-to-store and store-to-store flow passes through a
   process, following DFD rules.

