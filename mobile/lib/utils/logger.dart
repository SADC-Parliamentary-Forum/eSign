import 'package:flutter/foundation.dart';

/// Logger utility that only outputs in debug mode.
/// Prevents sensitive error information from appearing in production builds.
class AppLogger {
  static void error(String context, dynamic error) {
    if (kDebugMode) {
      print('[$context] Error: $error');
    }
  }

  static void info(String context, String message) {
    if (kDebugMode) {
      print('[$context] $message');
    }
  }

  static void warning(String context, String message) {
    if (kDebugMode) {
      print('[$context] Warning: $message');
    }
  }
}
