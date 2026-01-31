import 'package:flutter/material.dart';
import '../services/api_service.dart';

class TemplateDetailScreen extends StatefulWidget {
  final String templateId;

  const TemplateDetailScreen({super.key, required this.templateId});

  @override
  State<TemplateDetailScreen> createState() => _TemplateDetailScreenState();
}

class _TemplateDetailScreenState extends State<TemplateDetailScreen> {
  Map<String, dynamic>? _template;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadTemplate();
  }

  Future<void> _loadTemplate() async {
    try {
      final template = await ApiService.getTemplate(widget.templateId);
      setState(() {
        _template = template;
        _isLoading = false;
      });
    } catch (e) {
      setState(() => _isLoading = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to load template: $e')),
        );
      }
    }
  }

  Future<void> _applyTemplate() async {
    // Show dialog to collect required fields
    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (context) => _ApplyTemplateDialog(template: _template!),
    );

    if (result != null) {
      try {
        await ApiService.applyTemplate(widget.templateId, result);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Template applied successfully!'),
              backgroundColor: Colors.green,
            ),
          );
          Navigator.pop(context, true);
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Failed to apply template: $e')),
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
          title: const Text('Template Details'),
          backgroundColor: Theme.of(context).colorScheme.primary,
          foregroundColor: Colors.white,
        ),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_template == null) {
      return Scaffold(
        appBar: AppBar(
          title: const Text('Template Details'),
          backgroundColor: Theme.of(context).colorScheme.primary,
          foregroundColor: Colors.white,
        ),
        body: const Center(child: Text('Template not found')),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Template Details'),
        backgroundColor: Theme.of(context).colorScheme.primary,
        foregroundColor: Colors.white,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _template!['name'] ?? 'Untitled Template',
                      style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 8),
                    if (_template!['description'] != null)
                      Text(
                        _template!['description'],
                        style: TextStyle(color: Colors.grey[600]),
                      ),
                    const SizedBox(height: 16),
                    if (_template!['category'] != null)
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                        decoration: BoxDecoration(
                          color: Colors.blue[50],
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: Colors.blue),
                        ),
                        child: Text(
                          _template!['category'],
                          style: const TextStyle(
                            color: Colors.blue,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            if (_template!['workflow_type'] != null) ...[
              Card(
                child: ListTile(
                  leading: const Icon(Icons.work),
                  title: const Text('Workflow Type'),
                  subtitle: Text(_template!['workflow_type']),
                ),
              ),
              const SizedBox(height: 16),
            ],
            FilledButton.icon(
              onPressed: _applyTemplate,
              icon: const Icon(Icons.add),
              label: const Text('Apply Template'),
              style: FilledButton.styleFrom(
                padding: const EdgeInsets.all(16),
                backgroundColor: const Color(0xFF3182CE),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ApplyTemplateDialog extends StatefulWidget {
  final Map<String, dynamic> template;

  const _ApplyTemplateDialog({required this.template});

  @override
  State<_ApplyTemplateDialog> createState() => _ApplyTemplateDialogState();
}

class _ApplyTemplateDialogState extends State<_ApplyTemplateDialog> {
  final _titleController = TextEditingController();
  final _formKey = GlobalKey<FormState>();

  @override
  void dispose() {
    _titleController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Apply Template'),
      content: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextFormField(
                controller: _titleController,
                decoration: const InputDecoration(
                  labelText: 'Document Title',
                  border: OutlineInputBorder(),
                ),
                validator: (value) {
                  if (value == null || value.trim().isEmpty) {
                    return 'Please enter a title';
                  }
                  return null;
                },
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: () {
            if (_formKey.currentState!.validate()) {
              Navigator.pop(context, {
                'title': _titleController.text.trim(),
              });
            }
          },
          child: const Text('Apply'),
        ),
      ],
    );
  }
}
