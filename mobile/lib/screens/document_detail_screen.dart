import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:pdfx/pdfx.dart';
import '../services/api_service.dart';
import 'signing_screen.dart';
import '../widgets/reject_dialog.dart';
import '../widgets/activity_feed_list.dart';
import 'package:flutter_animate/flutter_animate.dart';

class DocumentDetailScreen extends StatefulWidget {
  final String documentId;

  const DocumentDetailScreen({super.key, required this.documentId});

  @override
  State<DocumentDetailScreen> createState() => _DocumentDetailScreenState();
}

class _DocumentDetailScreenState extends State<DocumentDetailScreen> {
  Map<String, dynamic>? _document;
  bool _isLoading = true;
  String? _error;
  PdfController? _pdfController;
  bool _isLoadingPdf = true;

  @override
  void initState() {
    super.initState();
    _loadDocument();
  }

  @override
  void dispose() {
    _pdfController?.dispose();
    super.dispose();
  }

  Future<void> _loadDocument() async {
    try {
      final doc = await ApiService.getDocument(widget.documentId);
      if (doc != null) {
        if (mounted) {
          setState(() {
            _document = doc;
          });
        }
        _loadPdf();
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e.toString();
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _loadPdf() async {
    try {
      final bytes = await ApiService.downloadDocumentPdf(widget.documentId);
      if (bytes != null) {
        _pdfController = PdfController(
          document: PdfDocument.openData(bytes),
        );
        if (mounted) {
          setState(() {
            _isLoadingPdf = false;
            _isLoading = false;
          });
        }
      } else {
        if (mounted) {
          setState(() {
            _isLoadingPdf = false;
            _isLoading = false;
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoadingPdf = false;
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _showRejectDialog() async {
    final reason = await showDialog<String>(
      context: context,
      builder: (context) => const RejectDialog(),
    );

    if (reason != null) {
      try {
        await ApiService.rejectDocument(
          widget.documentId,
          reason,
        );
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Document rejected successfully'),
              backgroundColor: Colors.green,
            ),
          );
          _loadDocument(); // Refresh status
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Failed to reject document: $e')),
          );
        }
      }
    }
  }

  Color _getStatusColor(String? status) {
    switch (status?.toLowerCase()) {
      case 'signed':
        return Colors.green;
      case 'pending':
        return Colors.orange;
      case 'rejected':
        return Colors.red;
      case 'draft':
        return Colors.blue;
      default:
        return Colors.grey;
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading && _document == null) {
      return Scaffold(
        appBar: AppBar(
          title: const Text('Document Details'),
          backgroundColor: Theme.of(context).colorScheme.primary,
          foregroundColor: Colors.white,
        ),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_error != null || _document == null) {
      return Scaffold(
        appBar: AppBar(
          title: const Text('Document Details'),
          backgroundColor: Theme.of(context).colorScheme.primary,
          foregroundColor: Colors.white,
        ),
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 64, color: Colors.red),
              const SizedBox(height: 16),
              Text(_error ?? 'Document not found'),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: () {
                  Navigator.pop(context);
                },
                child: const Text('Go Back'),
              ),
            ],
          ),
        ),
      );
    }

    final status = _document!['status'] ?? 'unknown';

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Document Details'),
          backgroundColor: Theme.of(context).colorScheme.primary,
          foregroundColor: Colors.white,
          bottom: const TabBar(
            labelColor: Colors.white,
            unselectedLabelColor: Colors.grey,
            indicatorColor: Colors.white,
            tabs: [
              Tab(text: 'Document'),
              Tab(text: 'Activity'),
            ],
          ),
          actions: [
            if (status == 'pending') ...[
              IconButton(
                icon: const Icon(Icons.cancel),
                onPressed: _showRejectDialog,
                tooltip: 'Reject',
              ),

              IconButton(
                icon: const Icon(Icons.edit),
                onPressed: () async {
                  final result = await Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => SigningScreen(
                        documentId: widget.documentId,
                        documentTitle: _document!['title'] ?? 'Document',
                      ),
                    ),
                  );
                  if (result == true) {
                    _loadDocument();
                  }
                },
                tooltip: 'Sign',
              ).animate(onPlay: (c) => c.repeat(reverse: true))
               .scaleXY(end: 1.2, duration: 800.ms)
               .effect(duration: 800.ms, curve: Curves.easeInOut), // Subtle pulse
            ],
          ],
        ),
        body: TabBarView(
          children: [
            _buildDocumentTab(),
            _buildActivityTab(),
          ],
        ),
      ),
    );
  }

  Widget _buildDocumentTab() {
    final status = _document!['status'] ?? 'unknown';
    final statusColor = _getStatusColor(status);

    return Column(
      children: [
        Container(
          padding: const EdgeInsets.all(16),
          color: Colors.white,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      _document!['title'] ?? 'Untitled Document',
                      style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    decoration: BoxDecoration(
                      color: statusColor.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: statusColor),
                    ),
                    child: Text(
                      status.toUpperCase(),
                      style: TextStyle(
                        color: statusColor,
                        fontWeight: FontWeight.bold,
                        fontSize: 12,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              _InfoRow(
                icon: Icons.folder,
                label: 'Department',
                value: _document!['department'] ?? 'General',
              ),
              const SizedBox(height: 8),
              _InfoRow(
                icon: Icons.calendar_today,
                label: 'Created',
                value: _document!['created_at'] != null
                    ? DateFormat('MMM dd, yyyy').format(DateTime.parse(_document!['created_at']))
                    : 'Unknown',
              ),
              if (_document!['signers'] != null) ...[
                const SizedBox(height: 16),
                const Text('Signers:', style: TextStyle(fontWeight: FontWeight.bold)),
                const SizedBox(height: 8),
                ...(_document!['signers'] as List).map((signer) => Padding(
                      padding: const EdgeInsets.only(bottom: 4),
                      child: Row(
                        children: [
                          Icon(
                            signer['signed_at'] != null ? Icons.check_circle : Icons.pending,
                            size: 16,
                            color: signer['signed_at'] != null ? Colors.green : Colors.orange,
                          ),
                          const SizedBox(width: 8),
                          Text(signer['email'] ?? 'Unknown'),
                        ],
                      ),
                    )),
              ],
            ],
          ),
        ),
        const Divider(height: 1),
        Expanded(
          child: _isLoadingPdf
              ? const Center(child: CircularProgressIndicator())
              : _pdfController != null
                  ? PdfView(
                      controller: _pdfController!,
                      scrollDirection: Axis.vertical,
                    )
                  : Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(Icons.error_outline, size: 48, color: Colors.grey),
                          const SizedBox(height: 16),
                          Text(
                            'Could not load PDF',
                            style: TextStyle(color: Colors.grey[600]),
                          ),
                        ],
                      ),
                    ),
        ),
      ],
    );
  }

  Widget _buildActivityTab() {
    return ActivityFeedList(documentId: widget.documentId);
  }
}

class _InfoRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;

  const _InfoRow({required this.icon, required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 16, color: Colors.grey[600]),
        const SizedBox(width: 8),
        Text(
          '$label: ',
          style: TextStyle(color: Colors.grey[600], fontSize: 14),
        ),
        Text(
          value,
          style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
        ),
      ],
    );
  }
}
