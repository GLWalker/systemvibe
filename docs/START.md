# SystemVibe Project Documentation & Directives

This `START.md` serves as the primary contractual and architectural directive for the SystemVibe project. Any agent or developer working on this codebase **must** adhere strictly to the rules defined below. These rules override global agent rules where they conflict.

## 1. Core Identity & Final Doctrine

SystemVibe is a governed, WordPress-native expert system for reasoning about, validating, generating, and safely deploying runtime changes. It is **not** a generic chatbot, a visual page builder, or an uncontrolled AI agent.

**The Immutable SystemVibe Doctrine:**

1. Inspection before automation.
2. Governance before execution.
3. Validation before deployment.

## 2. The Four Pillars of Architecture

1. **WordPress-Native Execution**
    - PHP Abilities (`@wordpress/abilities`) own authority.
    - WordPress Core UI (Command Palette, native notices) owns interaction. No custom floating AI chatbots or iframes.
    - JavaScript acts purely as transport and orchestration.

2. **Governance Before Automation**
    - Every action is inspected.
    - Every mutation is validated.
    - Every deployment is previewed.
    - Every operation is logged to immutable telemetry.

3. **Sandbox-First Architecture**
    - Generated artifacts never execute automatically.
    - Artifacts are first written to the quarantined runtime: `wp-content/systemvibe-private/generated/`.
    - Validation and hash-sealing are mandatory before Apply.
    - Deployment (Apply) is strictly separated from Activation.
    - Deployed artifacts live in `wp-content/plugins/systemvibe-generated/`.

4. **Deterministic AI Augmentation**
    - AI assists generation, but structured templates remain authoritative.
    - Freeform execution (e.g., executing raw user JS/PHP) is never trusted blindly.
    - Strict boundaries: No JSX, no Webpack/build steps. Classic WordPress global variables (`wp.element`, `wp.blocks`) only.

## 3. Telemetry & Security

- **Immutable Telemetry**: All scans, generation events, validation results, and apply operations must be logged to `wp-content/systemvibe-private/telemetry/`.
- **Integrity**: Generated artifacts are sealed with SHA256 manifests. Apply actions must verify these hashes to prevent tampering.
- **Path Security**: All file operations must use `wp_normalize_path` and `realpath` boundary checks to prevent path traversal.
- **Capabilities**: Enforce strict capability checks:
    - `manage_options` for Generate, Validate, Preview.
    - `edit_plugins` + `manage_options` for Apply.
    - `activate_plugins` for Activation.

## 4. Git Staging & Backup Directive

**CRITICAL:** Never run `git init`, `git commit`, or `git push` directly from the live DevKinsta `wp-content` directory. The live folder is for runtime execution and testing only.

When asked to push or backup SystemVibe:

1. Dynamically identify the DevKinsta site name (do not hardcode).
2. Sync a clean copy of the code to the global staging directory at `/Users/glwalker/DevKinsta/public/git/systemvibe` (excluding `runtime`, `logs`, `.git`, `.DS_Store`, etc.).
3. Create a clean `.zip` distributable beside it.
4. Perform all Git operations _exclusively_ from the `/public/git/systemvibe` directory.
