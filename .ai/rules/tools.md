---
paths:
  - 'app/Mcp/Tools/**'
---

# Tools

## MCP tools declare their own rules
An MCP tool declares its validation rules inline, like every FormRequest (see AGENTS.md "Backend Validation": requests are independent). Do not call a shared rules helper; the classes under app/Support/Requests/** predate this rule and will be refactored away. Business rules live in Actions/policies so web, API and MCP enforce them identically, and the parity tests in tests/Feature/Parity prove the three surfaces agree.
