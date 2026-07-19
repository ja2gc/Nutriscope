# Clinical Care — Current NCP/ADIME Flow

Verified against current RND pages and workflow helpers on **2026-07-19**.

## End-to-End Flow

```mermaid
flowchart TD
    A["RND web login"] --> B["Nutrition Care → Patients"]
    B --> C{"Patient exists?"}
    C -->|"No"| D["Create Patient and Start Assessment"]
    C -->|"Yes"| E["Open patient profile"]
    D --> F["New NCP cycle"]
    E --> G{"Continue or start cycle?"}
    G -->|"Continue"| H["Open existing ADIME record"]
    G -->|"Start"| F

    F --> I["Assessment"]
    H --> I
    I --> I1["Dietary"]
    I --> I2["Anthropometrics"]
    I --> I3["Client History"]
    I --> I4["Biochemical and Labs"]
    I --> I5["Referral and Screening"]
    I --> I6["Summary and Risk Review"]
    I4 --> I7["Optional supporting-file upload; no current OCR/autofill"]
    I5 --> I7
    I6 --> J{"Assessment save valid?"}
    J -->|"No"| I
    J -->|"Yes"| K["Diagnosis unlocked"]

    K --> L["Build Problem, Etiology, Signs/Symptoms"]
    L --> M["Review editable PES statement"]
    K --> N["Optional AI draft suggestions"]
    N --> O["Accept, edit, or dismiss"]
    O --> M
    M --> P["Save at least one Diagnosis"]

    P --> Q["Intervention unlocked"]
    Q --> Q1["Set goal and stage"]
    Q1 --> Q2["Backend-authoritative prescription autofill and trace"]
    Q2 --> Q3["Review/edit targets and food guidance"]
    Q3 --> Q4["Create, generate, or load patient meal plan"]
    Q4 --> Q5["Complete education, counseling, goals, encounter context"]
    Q5 --> R["Save care plan"]

    R --> S{"Patient returns for follow-up?"}
    S -->|"Not yet"| T["Care plan remains usable without Monitoring"]
    S -->|"Yes"| U["Monitoring Visit Log"]
    U --> V["Progress Trends vs baseline and targets"]
    V --> W["Save entry and next monitoring date"]
    W --> X{"Continue, revise, or close care?"}
    X -->|"Continue"| U
    X -->|"Revise"| Q

    T --> Y["Reports"]
    W --> Y
    Y --> Z["Preview live NCP Summary or Patient Menu Plan"]
    Z --> AA["Archive approved as-filed copy"]
```

## Implemented Navigation Gates

```mermaid
flowchart LR
    A["Assessment"] -->|"saved"| B["Diagnosis"]
    B -->|"one or more saved"| C["Intervention"]
    C -->|"saved"| D["Monitoring"]
```

- Diagnosis block reason: save Assessment first.
- Intervention block reason: save Assessment and at least one Diagnosis.
- Monitoring block reason: save Assessment, Diagnosis, and care plan first.
- Current code calls Monitoring follow-up/second-visit work but does not separately enforce a numeric visit count.

## Patient Record Structure

```mermaid
flowchart TD
    P["Patient Profile"] --> O["Overview"]
    P --> A["ADIME Records"]
    P --> F["Attachments"]
    A --> C1["NCP Cycle 1"]
    A --> C2["NCP Cycle 2+"]
    C1 --> S1["Assessment"]
    C1 --> S2["Diagnoses"]
    C1 --> S3["Intervention and meal plans"]
    C1 --> S4["Monitoring entries"]
    C1 --> S5["Structured activity"]
    F --> F1["Files grouped by NCP cycle"]
```

## Important Current Rules

- Edema present requires dry weight before Assessment save.
- Generated Assessment Summary is an editable draft; stale-source warning supports regenerate/undo.
- AI Diagnosis output is never accepted without RND action.
- Prescription calculation authority is Laravel backend; frontend trace explains the result.
- A cycle becomes deletion-protected once Assessment, Diagnosis, and Intervention all exist.
- Reports have live preview and frozen archived-copy states.

## Related Documents

- [RND Module](../rnd.md)
- [FAQ](../../FAQ.md)
- [Role How-To](../../ROLE-HOW-TO.md)
- [Storyboards](../../STORYBOARD.md)
