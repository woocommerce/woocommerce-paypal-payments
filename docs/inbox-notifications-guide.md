# WooCommerce Home Inbox Notifications Development Guide

This guide explains how to create and manage inbox notifications that appear in the WooCommerce Admin dashboard.

## Overview

The WooCommerce PayPal Payments plugin uses a structured system to create inbox notes through three main components: note definitions, note objects, and registration handling.

## Architecture

### 1. Define Inbox Notes

Inbox notes are defined as service configurations in `modules/ppcp-wc-gateway/services.php` (Service name: `wcgateway.settings.inbox-notes`). The system creates an array of `InboxNote` objects with the following properties:

- `title`: The note headline
- `content`: The note body text  
- `type`: Note type (e.g., `Note::E_WC_ADMIN_NOTE_INFORMATIONAL`)
- `name`: Unique identifier for the note
- `status`: Note status (e.g., `Note::E_WC_ADMIN_NOTE_UNACTIONED`)
- `is_enabled`: Boolean function to control visibility
- `action`: An `InboxNoteAction` object for user interactions

### 2. Create Inbox Note Objects

Each inbox note is created using the `InboxNoteFactory` and `InboxNote` class. The constructor requires all the properties listed above.

### 3. Register Inbox Notes

The `InboxNoteRegistrar` handles the registration process by:
- Creating WooCommerce `Note` objects from `InboxNote` definitions
- Saving notes to display in the admin inbox
- Managing note lifecycle (creation/deletion based on conditions)

### 4. Registration Hook

Inbox notes are registered via the `register_woo_inbox_notes` method in `WCGatewayModule`, which hooks into the `admin_init` action.

## Implementation Example

```php
// In services.php
'inbox-note.example' => static function ( ContainerInterface $container ): InboxNote {
    return $container->get( 'inbox-note.factory' )->create_note(
        __( 'Example Note Title', 'woocommerce-paypal-payments' ),
        __( 'This is the note content that appears in the inbox.', 'woocommerce-paypal-payments' ),
        Note::E_WC_ADMIN_NOTE_INFORMATIONAL,
        'example-note-unique-name',
        Note::E_WC_ADMIN_NOTE_UNACTIONED,
        static function () use ( $container ): bool {
            // Conditional logic to determine when note should be shown
            return true; // or your condition
        },
        new InboxNoteAction(
            'apply_now',
            __( 'Apply now', 'woocommerce-paypal-payments' ),
            'http://example.com/',
            Note::E_WC_ADMIN_NOTE_UNACTIONED,
            true
        )
    );
},

```

## Content Limitations

WooCommerce inbox notes have several restrictions:

### Character Limit
- Content is automatically truncated at **320 characters** with "..."
- No expansion option available in the UI
- Reference: [WooCommerce Developer Blog ↗](https://developer.woocommerce.com/2021/11/10/introducing-a-320-character-limit-to-inbox-notes/)

### HTML Restrictions
Only basic HTML tags are allowed:
- `<strong>`, `<em>` for emphasis
- `<a>` for links (with `href`, `rel`, `name`, `target`, `download` attributes)
- `<br>`, `<p>` for formatting
- Tags like `<sup>`, `<sub>`, `<span>` are stripped

### Workarounds
- Use asterisks (*) for emphasis when HTML tags aren't supported
- Keep messages concise and prioritize essential information
- Place most important content within the first 320 characters

## Automatic Cleanup

The system includes automatic cleanup functionality:
- Notes are deleted when their `is_enabled` condition becomes `false` (`InboxNoteRegistrar.php`)
- This prevents stale notifications from persisting in the admin

## Actions

Notes can include user actions defined through the `InboxNoteAction` class. Actions appear as buttons in the inbox note and can:
- Navigate to specific admin pages
- Trigger custom functionality
- Dismiss or acknowledge the notification

## Best Practices

1. **Use descriptive, unique names** for note identification
2. **Implement proper conditional logic** in the `is_enabled` function
3. **Keep content concise** due to the 320-character limit
4. **Test note visibility conditions** thoroughly
5. **Provide clear, actionable next steps** through note actions
6. **Consider cleanup scenarios** when notes should be removed

## Existing Examples

The codebase includes several inbox note implementations:
- PayPal Working Capital note
- Settings migration notices
- Feature announcements

These examples demonstrate conditional logic based on feature flags, user settings, and other criteria.
