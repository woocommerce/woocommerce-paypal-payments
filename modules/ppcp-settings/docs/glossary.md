# Glossary

This document provides definitions and explanations of key terms used in the plugin.

---

## Eligibility

**Eligibility** determines whether a merchant can access a specific feature within the plugin. It is a boolean value (`true` or `false`) that depends on certain criteria, such as:

- **Country**: The merchant's location or the country where their business operates.
- **Other Factors**: Additional conditions, such as subscription plans, business type, or compliance requirements.

If a merchant is **eligible** (`true`) for a feature, the feature will be visible and accessible in the plugin. If they are **not eligible** (`false`), the feature will be hidden or unavailable.

---

## Capability

**Capability** refers to the activation status of a feature for an eligible merchant. Even if a merchant is eligible for a feature, they may need to activate it in their PayPal dashboard to use it. Capability has two states:

- **Active**: The feature is enabled, and the merchant can configure and use it.
- **Inactive**: The feature is not enabled, and the merchant will be guided on how to activate it (e.g., through instructions or prompts).

Capability ensures that eligible merchants have control over which features they want to use and configure within the plugin.

---

### Example Workflow

1. A merchant is **eligible** for a feature based on their country and other factors.
2. If the feature is **active** (capability is enabled), the merchant can configure and use it.
3. If the feature is **inactive**, the plugin will provide instructions on how to activate it.

---
