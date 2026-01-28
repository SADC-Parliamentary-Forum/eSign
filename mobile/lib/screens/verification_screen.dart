import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../main.dart'; // For LoginScreen

class VerificationScreen extends StatefulWidget {
  final Uri uri;

  const VerificationScreen({super.key, required this.uri});

  @override
  State<VerificationScreen> createState() => _VerificationScreenState();
}

class _VerificationScreenState extends State<VerificationScreen> {
  bool _isLoading = true;
  String? _error;
  bool _success = false;

  @override
  void initState() {
    super.initState();
    _verify();
  }

  Future<void> _verify() async {
    // Extract params: /auth/verify-email?id=...&hash=...&expires=...&signature=...
    final params = widget.uri.queryParameters;
    final id = params['id'];
    final hash = params['hash'];
    final expires = params['expires'];
    final signature = params['signature'];

    if (id == null || hash == null || expires == null || signature == null) {
      setState(() {
        _isLoading = false;
        _error = 'Invalid verification link: Missing parameters';
      });
      return;
    }

    try {
      final success = await ApiService.verifyEmail(id, hash, expires, signature);
      setState(() {
        _isLoading = false;
        _success = success;
        if (!success) _error = 'Verification failed. Link may be expired.';
      });
    } catch (e) {
      setState(() {
        _isLoading = false;
        _error = 'Connection error: $e';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (_isLoading) ...[
                const CircularProgressIndicator(),
                const SizedBox(height: 24),
                const Text('Verifying your email...', style: TextStyle(fontSize: 18)),
              ] else if (_success) ...[
                const Icon(Icons.check_circle, color: Colors.green, size: 80),
                const SizedBox(height: 16),
                const Text('Verified!', style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
                const SizedBox(height: 8),
                const Text('Your email has been successfully verified.', textAlign: TextAlign.center),
                const SizedBox(height: 32),
                FilledButton(
                  onPressed: () {
                     // Navigate to Login (or Dashboard if we can auto-login)
                     // For now, go to Login
                     Navigator.of(context).pushAndRemoveUntil(
                       MaterialPageRoute(builder: (_) => const LoginScreen()),
                       (route) => false,
                     );
                  },
                  child: const Text('Go to Login'),
                ),
              ] else ...[
                const Icon(Icons.error, color: Colors.red, size: 80),
                const SizedBox(height: 16),
                const Text('Verification Failed', style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
                const SizedBox(height: 8),
                Text(_error ?? 'Unknown error', textAlign: TextAlign.center, style: const TextStyle(color: Colors.red)),
                const SizedBox(height: 32),
                OutlinedButton(
                  onPressed: () {
                     Navigator.of(context).pushAndRemoveUntil(
                       MaterialPageRoute(builder: (_) => const LoginScreen()),
                       (route) => false,
                     );
                  },
                  child: const Text('Back to Login'),
                ),
              ]
            ],
          ),
        ),
      ),
    );
  }
}
