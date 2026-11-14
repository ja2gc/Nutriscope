# NutriScope

NutriScope supports clinical nutrition care, meal planning and food-service operations. Laravel provides the shared API; Next.js provides the web console; Expo provides the food-service mobile app.

- **Maintaining or changing the system:** [Developer map](DEVELOPERS.md).
- **Installing locally:** [Local setup](docs/developers/platform/local-setup.md).
- **Hosting, accounts, DNS, HTTPS and turnover:** [Installation and turnover manual](docs/operations/README.md), [replacement VPS setup](docs/operations/vps-setup.md), [ownership and address cutover](docs/operations/turnover.md).
- **Configuring the five GitHub deployment secrets:** [Purpose, exact value sources and setup](docs/operations/environment.md#github-actions-repository-secrets-obtain-enter-and-replace).
- **Building and updating the Android app:** [APK/EAS release guide](docs/operations/mobile-release.md).
- **Using the system:** [Role user manual](docs/ROLE-HOW-TO.md), [page directory](docs/ROLE-HOW-TO.md#page-directory), [daily workflow](docs/ROLE-HOW-TO.md#daily-and-periodic-work), [FAQ](docs/FAQ.md).
- **Role manuals and daily routines:** [RND, FSS and Admin manual directory](docs/STORYBOARD.md).
- **Other references:** [Documentation index](docs/README.md).
- **Ownership and retained rights:** [Proposed project license terms](LICENSE.md).

Use committed lockfiles. Never use demo seeding, destructive migrations or production credentials for testing.

## Acronym legend

| Term | Meaning |
|---|---|
| RND | Registered Nutritionist-Dietitian |
| FSS | Food Service Staff; called kitchen staff on sign-in screens |
| NCP | Nutrition Care Process |
| ADIME | Assessment, Diagnosis, Intervention, Monitoring and Evaluation |
| PES | Problem, Etiology, Signs and Symptoms |
| AI | Artificial Intelligence; suggestions require professional review |
| SOP | Standard Operating Procedure |
| PO / OR | Purchase Order / Official Receipt |
| AIR / PPA | Acceptance and Inspection Report / Program Project Activity |
| USDA | United States Department of Agriculture; food nutrient data source |
| BMI / MUAC | Body Mass Index / Mid-Upper Arm Circumference |
| IBW / BMR / TEE | Ideal Body Weight / Basal Metabolic Rate / Total Energy Expenditure |
| NPO / TPN | Nothing by mouth / Total Parenteral Nutrition |
| API | Application Programming Interface |
| VPS | Virtual Private Server |
| DNS | Domain Name System |
| HTTPS / TLS / SSL | Secure HTTP / Transport Layer Security / Secure Sockets Layer; certificate-related terms |
| SSH | Secure Shell; remote server access |
| SMTP / MFA | Simple Mail Transfer Protocol / Multi-Factor Authentication |
| RAM / NVMe / GB | Random Access Memory / Non-Volatile Memory Express storage / Gigabytes |
| CI/CD | Continuous Integration / Continuous Delivery or Deployment |
| EAS / APK | Expo Application Services / Android application package |
| R2 / S3 | Cloudflare's object storage product / Amazon Simple Storage Service; R2 uses an S3-compatible API |
| PDF / CSV | Portable Document Format / Comma-Separated Values |
