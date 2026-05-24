Overview
1. System Purpose

NutriScope is a clinical nutrition system for hospital workflows that manages patient nutrition care, meal planning, and food service operations.

It combines deterministic algorithms with limited AI assistance for clinical reasoning support.

2. Core Users
RND — clinical nutrition care and NCP workflow
FSS — food service operations and inventory
Admin — role and system management
3. Main Modules
RND Clinical System (NCP, patients, interventions, monitoring)
Food Service System (inventory, menu cycles, meal prep)
Admin System (users, reports, audit, configuration)

4. Documentation Map

For details, refer to:

modules/ → role-based system behavior
logic/ → deterministic decision systems (meal planning algorithm, recommend/avoid engine)
ai-policy/ → AI usage rules
database-schema/ → data structure
security/ → access control
integrations/ → api integrations    
architecture/ → folder structure and stack