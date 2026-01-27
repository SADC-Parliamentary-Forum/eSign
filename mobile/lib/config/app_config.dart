import 'dart:convert';
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
      
      _instance = AppConfig._(
        apiBaseUrl: json['apiBaseUrl'] ?? 'http://10.0.2.2:8000/api', // default for Android emulator
        enableBiometric: json['features']?['biometricSigning'] ?? true,
        networkTimeoutMs: json['network']?['timeoutMs'] ?? 10000,
      );
    } catch (e) {
      // Fallback if file missing or parse error
      print('Config load failed: $e. Using defaults.');
      _instance = AppConfig._(
        apiBaseUrl: 'http://10.0.2.2:8000/api',
      );
    }
  }
}
