// Design tokens from mobile/Design/ (Tailwind mockups).
// Use these for all screens to match Design/ specifications.

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Design system from Design/ folder (sender_dashboard_overview_1, upload_and_add_recipients, etc.)
abstract class AppDesign {
  AppDesign._();

  // --- Colors (from tailwind.config in Design HTML) ---
  static const Color primary = Color(0xFF135BEC);
  static const Color primaryDark = Color(0xFF104AB8);

  static const Color backgroundLight = Color(0xFFF6F6F8);
  static const Color backgroundDark = Color(0xFF101622);

  static const Color surfaceLight = Color(0xFFFFFFFF);
  static const Color surfaceDark = Color(0xFF1A2233);

  static const Color textPrimaryLight = Color(0xFF0D121B);
  static const Color textPrimaryDark = Color(0xFFF8F9FC);
  static const Color textSecondaryLight = Color(0xFF4C669A);
  static const Color textSecondaryDark = Color(0xFF94A3B8);

  // Status / semantic
  static const Color statusPending = primary;
  static const Color statusCompleted = Color(0xFF16A34A); // green-600
  static const Color statusDraft = Color(0xFF6B7280); // gray-500
  static const Color statusDeclined = Color(0xFFEF4444); // red-500

  // --- Typography: Public Sans (Design font-display) ---
  static String get fontFamily => 'Public Sans';

  static TextStyle get displayBold => GoogleFonts.publicSans(
        fontWeight: FontWeight.bold,
        fontSize: 24,
        letterSpacing: -0.5,
      );
  static TextStyle get titleBold => GoogleFonts.publicSans(
        fontWeight: FontWeight.bold,
        fontSize: 18,
      );
  static TextStyle get bodyMedium => GoogleFonts.publicSans(
        fontWeight: FontWeight.w500,
        fontSize: 14,
      );
  static TextStyle get bodySmall => GoogleFonts.publicSans(
        fontWeight: FontWeight.w400,
        fontSize: 12,
      );
  static TextStyle get caption => GoogleFonts.publicSans(
        fontWeight: FontWeight.w500,
        fontSize: 12,
      );
  static TextStyle get labelUppercase => GoogleFonts.publicSans(
        fontWeight: FontWeight.w600,
        fontSize: 10,
        letterSpacing: 0.5,
      );

  // --- Radii (Design: rounded DEFAULT 0.25rem, lg 0.5rem, xl 0.75rem) ---
  static const double radiusSm = 4.0;
  static const double radiusMd = 8.0;
  static const double radiusLg = 12.0;
  static const double radiusXl = 16.0;

  // --- Spacing (Design padding px-4 = 16, py-6 = 24, etc.) ---
  static const double spacingXs = 4.0;
  static const double spacingSm = 8.0;
  static const double spacingMd = 16.0;
  static const double spacingLg = 24.0;
  static const double spacingXl = 32.0;

  // --- App branding (Design header) ---
  static const String appName = 'SecureSign';
  static const String appTagline = 'Government Gateway';
}

/// Theme data built from Design tokens.
class AppTheme {
  static ThemeData get light {
    return ThemeData(
      useMaterial3: true,
      colorScheme: ColorScheme.fromSeed(
        seedColor: AppDesign.primary,
        primary: AppDesign.primary,
        surface: AppDesign.surfaceLight,
        error: AppDesign.statusDeclined,
        onPrimary: Colors.white,
        onSurface: AppDesign.textPrimaryLight,
        onSurfaceVariant: AppDesign.textSecondaryLight,
        brightness: Brightness.light,
      ),
      scaffoldBackgroundColor: AppDesign.backgroundLight,
      appBarTheme: AppBarTheme(
        backgroundColor: AppDesign.surfaceLight,
        foregroundColor: AppDesign.textPrimaryLight,
        elevation: 0,
        scrolledUnderElevation: 0,
        titleTextStyle: AppDesign.titleBold.copyWith(
          color: AppDesign.textPrimaryLight,
          fontSize: 18,
        ),
      ),
      cardTheme: CardTheme(
        color: AppDesign.surfaceLight,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppDesign.radiusLg),
          side: BorderSide(
            color: Colors.grey.shade200,
            width: 1,
          ),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppDesign.primary,
          foregroundColor: Colors.white,
          elevation: 4,
          shadowColor: AppDesign.primary.withValues(alpha: 0.3),
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppDesign.radiusLg),
          ),
          textStyle: AppDesign.bodyMedium.copyWith(
            fontWeight: FontWeight.w600,
            color: Colors.white,
          ),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: AppDesign.surfaceLight,
        indicatorColor: AppDesign.primary.withValues(alpha: 0.15),
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.selected)) {
            return AppDesign.caption.copyWith(color: AppDesign.primary);
          }
          return AppDesign.caption.copyWith(color: AppDesign.textSecondaryLight);
        }),
        height: 72,
        elevation: 0,
      ),
      textTheme: TextTheme(
        titleLarge: AppDesign.displayBold.copyWith(color: AppDesign.textPrimaryLight),
        titleMedium: AppDesign.titleBold.copyWith(color: AppDesign.textPrimaryLight),
        bodyMedium: AppDesign.bodyMedium.copyWith(color: AppDesign.textPrimaryLight),
        bodySmall: AppDesign.bodySmall.copyWith(color: AppDesign.textSecondaryLight),
        labelSmall: AppDesign.caption.copyWith(color: AppDesign.textSecondaryLight),
      ),
      fontFamily: GoogleFonts.publicSans().fontFamily ?? 'Public Sans',
    );
  }

  static ThemeData get dark {
    return ThemeData(
      useMaterial3: true,
      colorScheme: ColorScheme.fromSeed(
        seedColor: AppDesign.primary,
        primary: AppDesign.primary,
        surface: AppDesign.surfaceDark,
        error: AppDesign.statusDeclined,
        onPrimary: Colors.white,
        onSurface: AppDesign.textPrimaryDark,
        onSurfaceVariant: AppDesign.textSecondaryDark,
        brightness: Brightness.dark,
      ),
      scaffoldBackgroundColor: AppDesign.backgroundDark,
      appBarTheme: AppBarTheme(
        backgroundColor: AppDesign.surfaceDark,
        foregroundColor: AppDesign.textPrimaryDark,
        elevation: 0,
        scrolledUnderElevation: 0,
        titleTextStyle: AppDesign.titleBold.copyWith(
          color: AppDesign.textPrimaryDark,
          fontSize: 18,
        ),
      ),
      cardTheme: CardTheme(
        color: AppDesign.surfaceDark,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppDesign.radiusLg),
          side: BorderSide(
            color: Colors.grey.shade800,
            width: 1,
          ),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppDesign.primary,
          foregroundColor: Colors.white,
          elevation: 4,
          shadowColor: AppDesign.primary.withValues(alpha: 0.3),
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppDesign.radiusLg),
          ),
          textStyle: AppDesign.bodyMedium.copyWith(
            fontWeight: FontWeight.w600,
            color: Colors.white,
          ),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: AppDesign.surfaceDark,
        indicatorColor: AppDesign.primary.withValues(alpha: 0.2),
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.selected)) {
            return AppDesign.caption.copyWith(color: AppDesign.primary);
          }
          return AppDesign.caption.copyWith(color: AppDesign.textSecondaryDark);
        }),
        height: 72,
        elevation: 0,
      ),
      textTheme: TextTheme(
        titleLarge: AppDesign.displayBold.copyWith(color: AppDesign.textPrimaryDark),
        titleMedium: AppDesign.titleBold.copyWith(color: AppDesign.textPrimaryDark),
        bodyMedium: AppDesign.bodyMedium.copyWith(color: AppDesign.textPrimaryDark),
        bodySmall: AppDesign.bodySmall.copyWith(color: AppDesign.textSecondaryDark),
        labelSmall: AppDesign.caption.copyWith(color: AppDesign.textSecondaryDark),
      ),
      fontFamily: GoogleFonts.publicSans().fontFamily ?? 'Public Sans',
    );
  }
}
