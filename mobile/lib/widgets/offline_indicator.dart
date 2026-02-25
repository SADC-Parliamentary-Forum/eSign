import 'dart:async';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import '../config/app_config.dart';
import '../theme/app_design.dart';

class OfflineIndicator extends StatefulWidget {
  const OfflineIndicator({super.key});

  @override
  State<OfflineIndicator> createState() => _OfflineIndicatorState();
}

class _OfflineIndicatorState extends State<OfflineIndicator> {
  bool _isOnline = true;
  Timer? _checkTimer;

  @override
  void initState() {
    super.initState();
    _checkConnection();
    _checkTimer = Timer.periodic(const Duration(seconds: 10), (_) => _checkConnection());
  }

  @override
  void dispose() {
    _checkTimer?.cancel();
    super.dispose();
  }

  Future<void> _checkConnection() async {
    try {
      final response = await http
          .get(Uri.parse('${AppConfig.instance.apiBaseUrl}/health'))
          .timeout(const Duration(seconds: 3));
      if (mounted && _isOnline != (response.statusCode == 200)) {
        setState(() => _isOnline = response.statusCode == 200);
      }
    } catch (e) {
      if (mounted && _isOnline) {
        setState(() => _isOnline = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isOnline) return const SizedBox.shrink();

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 16),
      color: AppDesign.statusPending,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.wifi_off, color: Colors.white, size: 16),
          const SizedBox(width: 8),
          const Text(
            'Offline Mode - Showing cached data',
            style: TextStyle(color: Colors.white, fontSize: 12),
          ),
        ],
      ),
    );
  }
}
