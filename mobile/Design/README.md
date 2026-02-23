# Mobile design reference

This folder contains the **source of truth** for mobile UI design. The Flutter app in `../lib` uses these specs for:

- **Colors:** primary `#135bec`, background/surface light and dark, text and status colors
- **Typography:** Public Sans (see `lib/theme/app_design.dart`)
- **Layout:** Header (SecureSign / Government Gateway), stats grid (Pending, Completed, Drafts, Declined), bottom nav (Dashboard, Documents, Contacts, Settings), upload flow, guest signer, profile/settings

Screens reference:

| Design file | Flutter screen(s) |
|-------------|-------------------|
| `sender_dashboard_overview_1/code.html` | Dashboard, main shell |
| `upload_and_add_recipients/code.html` | Upload document flow |
| `guest_signer_interface/code.html` | Guest signer / signing |
| `user_profile_and_hamburger_menu/code.html` | Profile, Settings, drawer |
| `signature_method_selection/code.html` | Signature creation (draw/type/upload) |
| `document_field_placement_editor/code.html` | Field placement (prepare) |
| `sms_otp_verification_screen/code.html` | OTP verification |
| `system_audit_logs_view/code.html` | Audit logs |

Theme and tokens are centralized in `lib/theme/app_design.dart`.
