import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

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
  
  static Future<void> load() async {
    // In a real scenario, you might load this from a specific file based on environment
    // For now, we'll try to load from config.json if it exists, otherwise fallback to defaults
    try {
      final configString = await rootBundle.loadString('assets/config.json');
      final json = jsonDecode(configString);
      
      String baseUrl = json['apiBaseUrl'] ?? '';
      if (kIsWeb && baseUrl.contains('10.0.2.2')) {
        baseUrl = 'http://localhost:8000/api';
      }
      if (baseUrl.isEmpty) {
        baseUrl = kIsWeb ? 'http://localhost:8000/api' : 'http://10.0.2.2:8000/api';
      }

      _instance = AppConfig._(
        apiBaseUrl: baseUrl,
        enableBiometric: json['features']?['biometricSigning'] ?? true,
        networkTimeoutMs: json['network']?['timeoutMs'] ?? 10000,
      );
    } catch (e) {
      // Fallback if file missing or parse error
      print('Config load failed: $e. Using defaults.');
      _instance = AppConfig._(
        apiBaseUrl: kIsWeb ? 'http://localhost:8000/api' : 'http://10.0.2.2:8000/api',
      );
    }
  }
}
