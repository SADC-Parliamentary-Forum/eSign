import 'dart:convert';
import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:signature/signature.dart';
import '../services/api_service.dart';
import 'package:http/http.dart' as http;
import '../config/app_config.dart';
import 'package:shared_preferences/shared_preferences.dart';

class SigningScreen extends StatefulWidget {
  final String documentId;
  final String documentTitle;

  const SigningScreen({
    super.key, 
    required this.documentId, 
    required this.documentTitle
  });

  @override
  State<SigningScreen> createState() => _SigningScreenState();
}

class _SigningScreenState extends State<SigningScreen> {
  final SignatureController _controller = SignatureController(
    penStrokeWidth: 3,
    penColor: Colors.black,
    exportBackgroundColor: Colors.transparent,
  );

  bool _isSigning = false;
  String? _errorMessage;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _submitSignature() async {
    if (_controller.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please sign before submitting')),
      );
      return;
    }

    setState(() {
      _isSigning = true;
      _errorMessage = null;
    });

    try {
      final Uint8List? data = await _controller.toPngBytes();
      if (data == null) return;

      final base64Signature = 'data:image/png;base64,' + base64Encode(data);
      
      final token = await ApiService.getToken();
      // The original line was: final url = Uri.parse('${ApiService.baseUrl}/documents/${widget.documentId}/sign');
      // The instruction implies replacing ApiService.baseUrl with AppConfig.instance.apiBaseUrl
      // The provided snippet for http.post is syntactically incorrect and seems to change the path significantly.
      // Assuming the intent is to update the base URL for the existing path.
      // However, the instruction's "Code Edit" snippet explicitly shows a different URL structure for the http.post call.
      // I will follow the provided "Code Edit" snippet as closely as possible, correcting syntax errors.
      // It seems to remove the 'documents/${widget.documentId}' part and adds '/sign/$token' to the base URL.
      // Also, 'rs:' should be 'headers:'. And '_token' should be 'token'.
      
      final response = await http.post(
        Uri.parse('${AppConfig.instance.apiBaseUrl}/sign/$token'), // Corrected _token to token and removed documents/${widget.documentId}
        headers: { // Corrected 'rs:' to 'headers:'
          'Authorization': 'Bearer $token',
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: jsonEncode({'signature_data': base64Signature}),
      );

      if (response.statusCode == 200) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Document signed successfully!')),
          );
          Navigator.pop(context, true); // Return true to refresh
        }
      } else {
        throw Exception('Failed to sign (Status: ${response.statusCode})');
      }
    } catch (e) {
      setState(() => _errorMessage = e.toString());
    } finally {
      setState(() => _isSigning = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Sign: ${widget.documentTitle}'),
        backgroundColor: const Color(0xFF2D3748),
        foregroundColor: Colors.white,
      ),
      body: Column(
        children: [
          Expanded(
            child: Container(
              color: Colors.grey[200],
              child: Center(
                child: Text(
                  'PDF Preview Placeholder\n(Use flutter_pdfview or Syncfusion_flutter_pdfviewer)',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Colors.grey[600]),
                ),
              ),
            ),
          ),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: const BoxDecoration(
              color: Colors.white,
              boxShadow: [BoxShadow(blurRadius: 5, color: Colors.black12)],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text('Draw your signature below:', style: TextStyle(fontWeight: FontWeight.bold)),
                const SizedBox(height: 8),
                Container(
                  decoration: BoxDecoration(
                    border: Border.all(color: Colors.grey),
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: Signature(
                    controller: _controller,
                    height: 200,
                    backgroundColor: Colors.white,
                  ),
                ),
                const SizedBox(height: 16),
                if (_errorMessage != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8.0),
                    child: Text(_errorMessage!, style: const TextStyle(color: Colors.red)),
                  ),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: _isSigning ? null : () => _controller.clear(),
                        child: const Text('Clear'),
                      ),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      flex: 2,
                      child: FilledButton(
                        onPressed: _isSigning ? null : _submitSignature,
                        style: FilledButton.styleFrom(
                          backgroundColor: const Color(0xFF2F855A), // Green
                        ),
                        child: _isSigning 
                          ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)) 
                          : const Text('Confirm Signature'),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
