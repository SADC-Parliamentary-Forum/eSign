import 'package:flutter/material.dart';
import '../services/api_service.dart';
import 'template_detail_screen.dart';
import '../widgets/loading_skeleton.dart';
import '../widgets/error_widget.dart';
import '../widgets/premium_card.dart';

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
      if (!mounted) return;
      setState(() {
        _templates = templates;
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;
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
    final scheme = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Templates'),
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
                            return PremiumCard(
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
                              child: Row(
                                children: [
                                  Container(
                                    width: 44,
                                    height: 44,
                                    decoration: BoxDecoration(
                                      color: scheme.secondary.withOpacity(0.12),
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                    child: Icon(Icons.description, color: scheme.secondary),
                                  ),
                                  const SizedBox(width: 14),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          template['name'] ?? 'Untitled Template',
                                          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
                                        ),
                                        const SizedBox(height: 4),
                                        Text(
                                          template['description'] ?? '',
                                          maxLines: 2,
                                          overflow: TextOverflow.ellipsis,
                                          style: TextStyle(color: Colors.grey[600]),
                                        ),
                                        if (template['category'] != null) ...[
                                          const SizedBox(height: 6),
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                            decoration: BoxDecoration(
                                              color: scheme.primary.withOpacity(0.08),
                                              borderRadius: BorderRadius.circular(999),
                                            ),
                                            child: Text(
                                              template['category'],
                                              style: TextStyle(
                                                fontSize: 11,
                                                color: scheme.primary,
                                                fontWeight: FontWeight.w600,
                                              ),
                                            ),
                                          ),
                                        ],
                                      ],
                                    ),
                                  ),
                                  Icon(Icons.chevron_right, color: Colors.grey[400]),
                                ],
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
    final scheme = Theme.of(context).colorScheme;
    return FilterChip(
      label: Text(label),
      selected: isSelected,
      onSelected: (_) => onTap(),
      selectedColor: scheme.secondary.withOpacity(0.15),
      checkmarkColor: scheme.primary,
    );
  }
}
