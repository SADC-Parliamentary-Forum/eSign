import 'package:flutter/foundation.dart';

import 'app_config_loader_io.dart' if (dart.library.html) 'app_config_loader_web.dart' as config_loader;

class AppConfig {
  static AppConfig? _instance;

  final String apiBaseUrl;
  final bool enableBiometric;
  final int networkTimeoutMs;

  AppConfig._({
    required this.apiBaseUrl,
    this.enableBiometric = true,
    this.networkTimeoutMs = 10000,
  });

  static AppConfig get instance {
    if (_instance == null) {
      throw Exception('AppConfig not initialized. Call AppConfig.load() first.');
    }
    return _instance!;
  }

  static const String productionApiBaseUrl = 'https://esign.sadcpf.org/api';

  static String get _defaultApiBaseUrl =>
      kIsWeb ? productionApiBaseUrl : productionApiBaseUrl;

  static Future<void> load() async {
    try {
      final json = await config_loader.loadConfigJson();
      _instance = AppConfig._(
        apiBaseUrl: json['apiBaseUrl']?.toString() ?? _defaultApiBaseUrl,
        enableBiometric: json['features']?['biometricSigning'] == true,
        networkTimeoutMs: (json['network']?['timeoutMs'] is int)
            ? (json['network']!['timeoutMs'] as int)
            : 10000,
      );
    } catch (e) {
      if (kDebugMode) {
        print('Config load failed: $e. Using defaults.');
      }
      _instance = AppConfig._(apiBaseUrl: _defaultApiBaseUrl);
    }
  }
}
