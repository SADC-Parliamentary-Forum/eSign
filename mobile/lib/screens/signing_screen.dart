import 'dart:convert';
import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:signature/signature.dart';
import '../services/api_service.dart';
import '../widgets/glass_app_bar.dart';
import '../widgets/premium_card.dart';
import '../widgets/success_seal_overlay.dart';

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

    await HapticFeedback.mediumImpact();

    setState(() {
      _isSigning = true;
      _errorMessage = null;
    });

    try {
      final Uint8List? data = await _controller.toPngBytes();
      if (data == null) return;
      
      final base64Signature = 'data:image/png;base64,${base64Encode(data)}';
      
      await ApiService.signDocument(
        widget.documentId,
        base64Signature,
      );

      if (mounted) {
        // Phase 3: Seal Animation
        await showDialog(
          context: context,
          barrierDismissible: false,
          builder: (context) => SuccessSealOverlay(
            onCompleted: () => Navigator.pop(context),
          ),
        );
        
        if (mounted) {
            // Phase 4: Archive (Pop with result)
            Navigator.pop(context, true); 
        }
      }
    } catch (e) {
      setState(() => _errorMessage = e.toString());
    } finally {
      if (mounted) setState(() => _isSigning = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      extendBodyBehindAppBar: true,
      appBar: GlassAppBar(
        title: 'Sign: ${widget.documentTitle}',
      ),
      body: Column(
        children: [
          SizedBox(height: kToolbarHeight + 40),
          Expanded(
            child: Container(
              color: Colors.grey[100],
              child: Center(
                child: Text(
                  'Document Preview would go here',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Colors.grey[600]),
                ),
              ),
            ),
          ),
          PremiumCard(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text('Draw your signature below:', style: TextStyle(fontWeight: FontWeight.bold)),
                const SizedBox(height: 8),
                Container(
                  decoration: BoxDecoration(
                    border: Border.all(color: Colors.grey.shade300),
                    borderRadius: BorderRadius.circular(12),
                    color: Colors.grey.shade50,
                  ),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: Signature(
                      controller: _controller,
                      height: 200,
                      backgroundColor: Colors.transparent,
                    ),
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
                          backgroundColor: Theme.of(context).colorScheme.primary,
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
