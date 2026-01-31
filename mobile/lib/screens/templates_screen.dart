import 'package:flutter/material.dart';
import '../services/api_service.dart';
import 'template_detail_screen.dart';
import '../widgets/loading_skeleton.dart';
import '../widgets/error_widget.dart';

class TemplatesScreen extends StatefulWidget {
  const TemplatesScreen({super.key});

  @override
  State<TemplatesScreen> createState() => _TemplatesScreenState();
}

class _TemplatesScreenState extends State<TemplatesScreen> {
  List<dynamic> _templates = [];
  bool _isLoading = true;
  bool _error = false;
  String? _errorMessage;
  String _selectedCategory = 'All';

  @override
  void initState() {
    super.initState();
    _loadTemplates();
  }

  Future<void> _loadTemplates() async {
    setState(() {
      _isLoading = true;
      _error = false;
      _errorMessage = null;
    });
    try {
      final templates = await ApiService.getTemplates();
      setState(() {
        _templates = templates;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _isLoading = false;
        _error = true;
        _errorMessage = e.toString().replaceAll('Exception: ', '');
      });
    }
  }

  List<dynamic> get _filteredTemplates {
    if (_selectedCategory == 'All') return _templates;
    return _templates.where((t) => t['category'] == _selectedCategory).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Templates'),
        backgroundColor: const Color(0xFF2D3748),
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadTemplates,
          ),
        ],
      ),
      body: Column(
        children: [
          Container(
            height: 50,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: ListView(
              scrollDirection: Axis.horizontal,
              children: [
                _CategoryChip(
                  label: 'All',
                  isSelected: _selectedCategory == 'All',
                  onTap: () => setState(() => _selectedCategory = 'All'),
                ),
                const SizedBox(width: 8),
                _CategoryChip(
                  label: 'Contract',
                  isSelected: _selectedCategory == 'Contract',
                  onTap: () => setState(() => _selectedCategory = 'Contract'),
                ),
                const SizedBox(width: 8),
                _CategoryChip(
                  label: 'Agreement',
                  isSelected: _selectedCategory == 'Agreement',
                  onTap: () => setState(() => _selectedCategory = 'Agreement'),
                ),
                const SizedBox(width: 8),
                _CategoryChip(
                  label: 'Form',
                  isSelected: _selectedCategory == 'Form',
                  onTap: () => setState(() => _selectedCategory = 'Form'),
                ),
              ],
            ),
          ),
          Expanded(
            child: _isLoading
                ? const ListSkeleton(itemCount: 5)
                : _error
                    ? ErrorRetryWidget(
                        message: _errorMessage ?? 'Failed to load templates',
                        onRetry: _loadTemplates,
                      )
                    : _filteredTemplates.isEmpty
                        ? const EmptyStateWidget(
                            icon: Icons.description,
                            title: 'No templates found',
                            subtitle: 'Templates will appear here when available',
                          )
                        : RefreshIndicator(
                        onRefresh: _loadTemplates,
                        child: ListView.builder(
                          padding: const EdgeInsets.all(16),
                          itemCount: _filteredTemplates.length,
                          itemBuilder: (context, index) {
                            final template = _filteredTemplates[index];
                            return Card(
                              margin: const EdgeInsets.only(bottom: 12),
                              child: ListTile(
                                leading: CircleAvatar(
                                  backgroundColor: Colors.blue[100],
                                  child: const Icon(Icons.description, color: Colors.blue),
                                ),
                                title: Text(
                                  template['name'] ?? 'Untitled Template',
                                  style: const TextStyle(fontWeight: FontWeight.w600),
                                ),
                                subtitle: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    const SizedBox(height: 4),
                                    Text(template['description'] ?? ''),
                                    const SizedBox(height: 4),
                                    if (template['category'] != null)
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                        decoration: BoxDecoration(
                                          color: Colors.blue[50],
                                          borderRadius: BorderRadius.circular(4),
                                        ),
                                        child: Text(
                                          template['category'],
                                          style: TextStyle(
                                            fontSize: 12,
                                            color: Colors.blue[700],
                                            fontWeight: FontWeight.w500,
                                          ),
                                        ),
                                      ),
                                  ],
                                ),
                                trailing: const Icon(Icons.chevron_right),
                                onTap: () {
                                  Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                      builder: (_) => TemplateDetailScreen(
                                        templateId: template['id'].toString(),
                                      ),
                                    ),
                                  );
                                },
                              ),
                            );
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }
}

class _CategoryChip extends StatelessWidget {
  final String label;
  final bool isSelected;
  final VoidCallback onTap;

  const _CategoryChip({
    required this.label,
    required this.isSelected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return FilterChip(
      label: Text(label),
      selected: isSelected,
      onSelected: (_) => onTap(),
      selectedColor: Colors.blue[100],
      checkmarkColor: Colors.blue[700],
    );
  }
}
