import 'package:flutter/material.dart';
import 'package:signature/signature.dart';
import '../services/api_service.dart';
import '../theme/app_design.dart';
import 'dart:convert';

class GuestSignerScreen extends StatefulWidget {
  final String token;

  const GuestSignerScreen({super.key, required this.token});

  @override
  State<GuestSignerScreen> createState() => _GuestSignerScreenState();
}

class _GuestSignerScreenState extends State<GuestSignerScreen> {
  Map<String, dynamic>? _document;
  bool _isLoading = true;
  final SignatureController _controller = SignatureController(
    penStrokeWidth: 3,
    penColor: Colors.black,
    exportBackgroundColor: Colors.transparent,
  );
  bool _isSigning = false;
  String? _errorMessage;

  // Amount-in-words state
  String? _expectedAmountWords;
  final TextEditingController _amountWordsController = TextEditingController();
  bool? _amountWordsMatch;

  bool get _hasAmountField {
    final fields = _document?['fields'] as List?;
    if (fields == null) return false;
    return fields.any((f) => f['type'] == 'AMOUNT_IN_WORDS');
  }

  double? get _documentAmount {
    final amount = _document?['amount'];
    if (amount == null) return null;
    return double.tryParse(amount.toString());
  }

  @override
  void initState() {
    super.initState();
    _loadDocument();
    _amountWordsController.addListener(_onAmountWordsChanged);
  }

  @override
  void dispose() {
    _controller.dispose();
    _amountWordsController.dispose();
    super.dispose();
  }

  Future<void> _loadDocument() async {
    try {
      final doc = await ApiService.getSignerDocument(widget.token);
      setState(() {
        _document = doc;
        _isLoading = false;
      });
      // Fetch the canonical word form from the PDF for hint display
      final docId = doc['id']?.toString();
      if (docId != null && _hasAmountField) {
        _fetchExpectedWords(docId);
      }
    } catch (e) {
      setState(() {
        _errorMessage = e.toString();
        _isLoading = false;
      });
    }
  }

  Future<void> _fetchExpectedWords(String documentId) async {
    try {
      final result = await ApiService.getDocumentAmountWords(documentId);
      if (mounted) {
        setState(() => _expectedAmountWords = result['words'] as String?);
      }
    } catch (_) {
      // Non-critical — hint will just not appear
    }
  }

  void _onAmountWordsChanged() {
    if (_expectedAmountWords == null) return;
    final typed = _amountWordsController.text.trim();
    final expected = _expectedAmountWords!.trim();
    setState(() {
      _amountWordsMatch = typed.toLowerCase() == expected.toLowerCase();
    });
  }

  Future<void> _signDocument() async {
    // If this document has an AMOUNT_IN_WORDS field, validate it first
    if (_hasAmountField) {
      if (_amountWordsController.text.trim().isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Please enter the amount in words before signing.'),
            backgroundColor: Colors.orange,
          ),
        );
        return;
      }
      if (_amountWordsMatch == false) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'Amount in words does not match.\nExpected: "$_expectedAmountWords"',
            ),
            backgroundColor: Colors.red,
            duration: const Duration(seconds: 5),
          ),
        );
        return;
      }
    }

    if (_controller.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please draw your signature before submitting')),
      );
      return;
    }

    setState(() {
      _isSigning = true;
      _errorMessage = null;
    });

    try {
      final data = await _controller.toPngBytes();
      if (data != null) {
        final base64Signature = 'data:image/png;base64,${base64Encode(data)}';
        final amountWords = _hasAmountField ? _amountWordsController.text.trim() : null;
        await ApiService.signAsGuest(widget.token, base64Signature, amountInWords: amountWords);

        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Document signed successfully!'),
              backgroundColor: Colors.green,
            ),
          );
          Navigator.of(context).popUntil((route) => route.isFirst);
        }
      }
    } catch (e) {
      setState(() {
        _errorMessage = e.toString().replaceAll('Exception: ', '');
      });
    } finally {
      if (mounted) setState(() => _isSigning = false);
    }
  }

  Future<void> _declineDocument() async {
    final reasonController = TextEditingController();
    final result = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Decline Document'),
        content: TextField(
          controller: reasonController,
          decoration: const InputDecoration(
            labelText: 'Reason (Optional)',
            border: OutlineInputBorder(),
          ),
          maxLines: 3,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, reasonController.text),
            style: FilledButton.styleFrom(backgroundColor: Colors.red),
            child: const Text('Decline'),
          ),
        ],
      ),
    );

    if (result != null) {
      try {
        await ApiService.declineAsGuest(widget.token, result);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Document declined')),
          );
          Navigator.of(context).popUntil((route) => route.isFirst);
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Failed to decline: $e')),
          );
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Scaffold(
        appBar: AppBar(
          title: const Text('Sign Document'),
          backgroundColor: AppDesign.primary,
          foregroundColor: Colors.white,
        ),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_errorMessage != null && _document == null) {
      return Scaffold(
        appBar: AppBar(
          title: const Text('Sign Document'),
          backgroundColor: AppDesign.primary,
          foregroundColor: Colors.white,
        ),
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 64, color: Colors.red),
              const SizedBox(height: 16),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 24),
                child: Text(_errorMessage!, textAlign: TextAlign.center),
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Go Back'),
              ),
            ],
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: Text(_document!['title'] ?? 'Sign Document'),
        backgroundColor: AppDesign.primary,
        foregroundColor: Colors.white,
      ),
      body: Column(
        children: [
          // PDF Preview
          Expanded(
            child: Container(
              color: Colors.grey[200],
              child: Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(Icons.picture_as_pdf, size: 64, color: Colors.grey),
                    const SizedBox(height: 16),
                    const Text('PDF Preview'),
                    const SizedBox(height: 8),
                    Text(
                      _document!['title'] ?? 'Document',
                      style: TextStyle(color: Colors.grey[600]),
                    ),
                  ],
                ),
              ),
            ),
          ),

          // Signing Controls
          Container(
            padding: const EdgeInsets.all(16),
            decoration: const BoxDecoration(
              color: Colors.white,
              boxShadow: [BoxShadow(blurRadius: 5, color: Colors.black12)],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [

                // ─── Amount in Words Field ───────────────────────────────
                if (_hasAmountField) ...[
                  _AmountInWordsField(
                    controller: _amountWordsController,
                    expectedWords: _expectedAmountWords,
                    numericAmount: _documentAmount,
                    isMatch: _amountWordsMatch,
                  ),
                  const SizedBox(height: 16),
                ],

                // ─── Signature Pad ───────────────────────────────────────
                const Text(
                  'Draw your signature below:',
                  style: TextStyle(fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 8),
                Container(
                  decoration: BoxDecoration(
                    border: Border.all(color: Colors.grey),
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: Signature(
                    controller: _controller,
                    height: 160,
                    backgroundColor: Colors.white,
                  ),
                ),

                if (_errorMessage != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 8.0),
                    child: Text(
                      _errorMessage!,
                      style: const TextStyle(color: Colors.red),
                    ),
                  ),

                const SizedBox(height: 16),

                // ─── Actions ─────────────────────────────────────────────
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: _isSigning ? null : _declineDocument,
                        style: OutlinedButton.styleFrom(foregroundColor: Colors.red),
                        child: const Text('Decline'),
                      ),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      flex: 2,
                      child: FilledButton(
                        onPressed: _isSigning ? null : _signDocument,
                        style: FilledButton.styleFrom(
                          backgroundColor: AppDesign.statusCompleted,
                        ),
                        child: _isSigning
                            ? const SizedBox(
                                height: 20,
                                width: 20,
                                child: CircularProgressIndicator(
                                  color: Colors.white,
                                  strokeWidth: 2,
                                ),
                              )
                            : const Text('Sign Document'),
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

// ─── Amount in Words Input Widget ─────────────────────────────────────────────

class _AmountInWordsField extends StatelessWidget {
  final TextEditingController controller;
  final String? expectedWords;
  final double? numericAmount;
  final bool? isMatch;

  const _AmountInWordsField({
    required this.controller,
    required this.expectedWords,
    required this.numericAmount,
    required this.isMatch,
  });

  Color get _borderColor {
    if (isMatch == null || controller.text.isEmpty) return Colors.grey.shade400;
    return isMatch! ? Colors.green : Colors.red;
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Header row with amount badge
        Row(
          children: [
            const Icon(Icons.payments_outlined, size: 18, color: Color(0xFF1e3a5f)),
            const SizedBox(width: 6),
            const Text(
              'Amount in Words',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
            ),
            const Spacer(),
            if (numericAmount != null)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: const Color(0xFF1e3a5f).withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  'ZAR ${numericAmount!.toStringAsFixed(2)}',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF1e3a5f),
                  ),
                ),
              ),
          ],
        ),
        const SizedBox(height: 6),

        // Helper text showing the expected format
        if (expectedWords != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 6),
            child: Text(
              'Expected: $expectedWords',
              style: TextStyle(fontSize: 11, color: Colors.grey[600], fontStyle: FontStyle.italic),
            ),
          ),

        // Text input
        AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          decoration: BoxDecoration(
            border: Border.all(color: _borderColor, width: 1.5),
            borderRadius: BorderRadius.circular(8),
          ),
          child: TextField(
            controller: controller,
            decoration: InputDecoration(
              hintText: 'e.g. One Thousand Five Hundred Rand and Zero Cents',
              hintStyle: TextStyle(fontSize: 12, color: Colors.grey[400]),
              border: InputBorder.none,
              contentPadding: const EdgeInsets.all(12),
              suffixIcon: isMatch == null
                  ? null
                  : Icon(
                      isMatch! ? Icons.check_circle : Icons.cancel,
                      color: isMatch! ? Colors.green : Colors.red,
                    ),
            ),
            textCapitalization: TextCapitalization.words,
            maxLines: 2,
          ),
        ),

        // Match/mismatch feedback
        if (isMatch != null && controller.text.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text(
              isMatch!
                  ? '✓ Amount in words matches'
                  : '✗ Does not match — please check your spelling',
              style: TextStyle(
                fontSize: 11,
                color: isMatch! ? Colors.green[700] : Colors.red[700],
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
      ],
    );
  }
}
