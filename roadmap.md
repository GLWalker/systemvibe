# SystemVibe Roadmap v2

## Vision

SystemVibe is evolving into a governed, WordPress-native expert system for analysis, validation, generation, and controlled deployment.

The platform is intentionally designed around four principles:

1. **WordPress-native execution**
    - PHP abilities own authority.
    - WordPress core UI owns interaction.
    - JavaScript acts as transport and orchestration only.

2. **Governance before automation**
    - Every action is inspected.
    - Every mutation is validated.
    - Every deployment is previewed.
    - Every operation is logged.

3. **Sandbox-first architecture**
    - Generated artifacts never execute automatically.
    - Validation is mandatory before apply.
    - Deployment is separated from activation.
    - Runtime isolation is treated as a security boundary.

4. **Deterministic AI augmentation**
    - AI assists generation.
    - Rules govern execution.
    - Structured templates remain authoritative.
    - Freeform execution is never trusted blindly.

---

# Current System Status

SystemVibe already includes:

- WordPress Abilities integration
- Command Palette integration
- Read-only inference scanning
- Accessibility and SEO scans
- Immutable telemetry
- Sandbox artifact generation
- Artifact validation pipeline
- Apply preview planning
- Controlled deployment pipeline
- Generated plugin activation
- Adversarial security testing
- Hash sealing and integrity verification
- Runtime isolation under `wp-content/systemvibe-private/`

The current architecture successfully completes this governed lifecycle:

```text
Generate
→ Validate
→ Preview
→ Apply
→ Activate
```

---

# Architectural Doctrine

## Runtime Hierarchy

```text
Scanner
  ↓
Facts
  ↓
Rule Evaluator
  ↓
Findings
  ↓
Abilities
  ↓
UI / Command Palette
```

## Source Priority

1. WordPress runtime introspection
2. Reflection APIs
3. Static token analysis
4. Heuristic inference
5. AI-assisted interpretation

## Security Doctrine

SystemVibe treats:

- generated code
- manifests
- telemetry
- sandbox artifacts
- plugin deployment
- AI output

as untrusted until validated.

Validation is not considered a complete sandbox.
It is a governed risk-reduction layer.

---

# Phase I — Foundation & Handshake

## Objective

Establish the core runtime and WordPress-native integration points.

## Completed

- Plugin bootstrap (`system-vibe.php`)
- Minimal loader architecture
- `Plugin::init()` orchestration
- Ability registration system
- Command Palette integration
- REST discoverability
- SystemVibe command category
- Ability execution bridge
- Zero-build JS strategy

## Result

SystemVibe successfully operates as a native WordPress runtime extension.

---

# Phase II — Inference Engine & Rules Layer

## Objective

Build a governed, read-only intelligence layer.

## Principles

- Runtime introspection first
- Reflection second
- Static token analysis third
- No mutation during inference

## Completed

### Scanner Infrastructure

SystemVibe can inspect:

- plugins
- themes
- blocks
- REST routes
- abilities
- editor structures
- post content

### Rules Engine

`rules.json` acts as the constitutional governance layer.

The evaluator produces:

- findings
- severity
- confidence
- remediation hints

### Telemetry

Immutable telemetry now records:

- scan timestamps
- WordPress version
- generation metadata
- validation events
- apply events
- adversarial tests

Telemetry is stored privately under:

```text
wp-content/systemvibe-private/
```

with:

- `.htaccess`
- `web.config`
- `index.php`

protection.

## Result

SystemVibe now possesses a governed intelligence layer capable of reasoning about WordPress state safely.

---

# Phase III — User Toolkit Abilities

## Objective

Expose governed capabilities through native WordPress interfaces.

## Completed Abilities

### Diagnostic Abilities

- Read-only scan
- Vibe scan
- SEO scan
- ARIA scan
- Block scan
- Latest scan summary

### Workspace Abilities

- Generate block
- List artifacts
- View artifact
- Validate artifact
- Preview apply
- Apply artifact
- Activate generated plugin

### Security & Governance

- Adversarial test suite
- Hash verification
- Staleness enforcement
- Manifest sealing

## UI Philosophy

SystemVibe intentionally avoids:

- floating AI assistants
- custom chat shells
- iframe copilots
- external dashboard frameworks

Instead it relies on:

- Command Palette
- WordPress notices
- Inspector panels
- native admin surfaces

## Result

SystemVibe behaves like a native WordPress capability layer rather than an external SaaS interface.

---

# Phase IV — Workspace Sandbox & Deployment Governance

## Objective

Create a fully governed runtime deployment pipeline.

## Completed

### Workspace Sandbox

Generated artifacts are isolated inside:

```text
wp-content/systemvibe-private/generated/
```

### Validation Pipeline

Artifacts undergo:

- JSON validation
- PHP token inspection
- JS heuristic inspection
- hash sealing
- integrity verification
- replay protection
- staleness enforcement

### Apply Preview

SystemVibe calculates:

- deployment plans
- overwrite detection
- plugin scaffolding
- filesystem method checks
- target path mapping

without mutating the filesystem.

### Apply Gate

Deployment is separated into:

1. Apply
2. Activate

This preserves human approval boundaries.

### Generated Runtime Plugin

Validated artifacts deploy into:

```text
wp-content/plugins/systemvibe-generated/
```

using:

- WP_Filesystem
- boundary enforcement
- recursive safe mkdir
- hash verification
- plugin version bumping

## Adversarial Hardening

SystemVibe currently passes:

- traversal attacks
- hash tampering
- stale replay attempts
- malformed manifests
- capability bypass checks
- oversized artifact attacks
- corrupted telemetry recovery

## Result

SystemVibe now functions as a secure WordPress workspace orchestrator.

---

# Phase V — Governed Generator Profiles

## Objective

Move generation from hardcoded demos toward structured, governed scaffolding.

## Completed

### Generator Modal

The native modal supports:

- profile selection
- intent notes
- governed generation

### Current Profiles

- Basic
- CTA
- FAQ
- Hero
- Testimonial

### Deterministic Generation

Generated blocks:

- use no JSX
- use no imports
- rely on classic WordPress globals
- avoid arbitrary execution
- remain template-driven

## Important Constraint

AI does not directly execute generated code.

Templates remain authoritative.

Intent acts only as governed metadata.

## Result

SystemVibe now supports structured, deterministic block generation safely.

---

# Phase VI — Architecture Refactor & Stabilization

## Objective

Reduce duplication and prepare the platform for scale.

## Planned

### Artifact Repository Layer

Introduce:

```text
ArtifactRepository
```

Responsibilities:

- manifest loading
- generation lookup
- artifact enumeration
- validation state
- hash retrieval
- telemetry linking

This removes duplication across:

- List
- View
- Validate
- Preview
- Apply

abilities.

### Template Extraction

Move profile templates out of:

```text
GenerateBlockAbility
```

into:

```text
/templates/profiles/
```

or:

```text
src/Templates/
```

This prepares for:

- richer block structures
- style variations
- dynamic placeholders
- future AI orchestration

### Ability Result Standardization

Introduce a unified:

```text
AbilityResult
```

response contract.

## Result

SystemVibe becomes easier to maintain, test, and extend.

---

# Phase VII — Smart Structured Generation

## Objective

Increase generation intelligence without sacrificing determinism.

## Direction

Before introducing freeform AI generation, SystemVibe will support:

- structured variables
- profile parameters
- template interpolation
- governed field mapping

Examples:

### CTA Profile

- heading
- description
- button label
- alignment
- color scheme

### FAQ Profile

- question count
- accordion behavior
- heading level

## Important Principle

AI suggestions may influence templates.

AI never bypasses validation or governance.

## Result

Generation becomes dramatically more useful while remaining safe and predictable.

---

# Phase VIII — Admin Governance Console

## Objective

Create a native operational home base.

## Planned Sections

```text
SystemVibe
 ├── Latest Scans
 ├── Findings
 ├── Generated Artifacts
 ├── Validation Status
 ├── Apply History
 ├── Telemetry
 ├── Adversarial Tests
 └── Workspace Runtime
```

## Design Rules

- Native WordPress admin UI only
- No custom AI chat windows
- No embedded copilots
- Minimal React surface area
- Governance-first presentation

## Result

SystemVibe gains operational visibility suitable for real-world workflows.

---

# Phase IX — LLM Runtime Integration

## Objective

Introduce governed AI augmentation.

## Important Constraint

LLMs will not directly control:

- filesystem writes
- plugin activation
- validation bypasses
- arbitrary execution

## Planned Architecture

```text
LLM
  ↓
Context Builder
  ↓
Facts
  ↓
Rules
  ↓
Governed Prompt
  ↓
Structured Output
  ↓
Validation
  ↓
Preview
  ↓
Apply
```

## Planned Capabilities

- template suggestions
- remediation proposals
- scan explanations
- code refinement
- governed block generation
- architecture guidance

## Provider Philosophy

Provider-agnostic by design.

Potential integrations:

- Ollama
- OpenAI
- Anthropic
- local runtimes
- future WordPress AI infrastructure

## Result

SystemVibe evolves into a governed WordPress expert system instead of a blind code generator.

---

# Long-Term Vision

SystemVibe is not intended to become:

- a generic chatbot
- a SaaS dashboard
- a visual page builder
- an uncontrolled AI agent

The long-term vision is:

```text
A governed WordPress-native expert system
for reasoning about, validating,
generating, and safely deploying
runtime changes.
```

Its core identity is:

- deterministic
- auditable
- secure
- extensible
- governance-first
- WordPress-native

---

# Current Priorities

## Immediate

1. ArtifactRepository extraction
2. Template extraction
3. AbilityResult standardization
4. Admin governance console

## Near-Term

1. Structured variable-driven generation
2. Expanded scan heuristics
3. Telemetry viewer
4. Findings explorer

## Long-Term

1. Governed LLM integration
2. Intelligent remediation
3. Site architecture analysis
4. Multi-artifact orchestration
5. Cross-plugin reasoning

---

# Final Doctrine

SystemVibe follows one immutable rule:

```text
Inspection before automation.
Governance before execution.
Validation before deployment.
```

That doctrine is what separates SystemVibe from a code generator and turns it into a trustworthy WordPress runtime intelligence system.
