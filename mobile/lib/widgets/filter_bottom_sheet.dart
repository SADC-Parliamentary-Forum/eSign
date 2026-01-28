import 'package:flutter/material.dart';

enum SortOption {
  newest,
  oldest,
  titleAsc,
  titleDesc,
  status,
}

class FilterBottomSheet extends StatefulWidget {
  final String? selectedStatus;
  final String? selectedDepartment;
  final SortOption? sortOption;
  final List<String> departments;
  final Function(String?, String?, SortOption?) onApply;

  const FilterBottomSheet({
    super.key,
    this.selectedStatus,
    this.selectedDepartment,
    this.sortOption,
    required this.departments,
    required this.onApply,
  });

  @override
  State<FilterBottomSheet> createState() => _FilterBottomSheetState();
}

class _FilterBottomSheetState extends State<FilterBottomSheet> {
  late String? _selectedStatus;
  late String? _selectedDepartment;
  late SortOption? _sortOption;

  @override
  void initState() {
    super.initState();
    _selectedStatus = widget.selectedStatus;
    _selectedDepartment = widget.selectedDepartment;
    _sortOption = widget.sortOption;
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Filters & Sort',
            style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 24),
          // Status Filter
          const Text('Status:', style: TextStyle(fontWeight: FontWeight.w600)),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            children: [
              _FilterChip(
                label: 'All',
                selected: _selectedStatus == null,
                onSelected: () => setState(() => _selectedStatus = null),
              ),
              _FilterChip(
                label: 'Pending',
                selected: _selectedStatus == 'pending',
                onSelected: () => setState(() => _selectedStatus = 'pending'),
              ),
              _FilterChip(
                label: 'Signed',
                selected: _selectedStatus == 'signed',
                onSelected: () => setState(() => _selectedStatus = 'signed'),
              ),
              _FilterChip(
                label: 'Draft',
                selected: _selectedStatus == 'draft',
                onSelected: () => setState(() => _selectedStatus = 'draft'),
              ),
            ],
          ),
          const SizedBox(height: 24),
          // Department Filter
          if (widget.departments.isNotEmpty) ...[
            const Text('Department:', style: TextStyle(fontWeight: FontWeight.w600)),
            const SizedBox(height: 8),
            DropdownButtonFormField<String>(
              value: _selectedDepartment,
              decoration: const InputDecoration(
                border: OutlineInputBorder(),
                hintText: 'All Departments',
              ),
              items: [
                const DropdownMenuItem(value: null, child: Text('All Departments')),
                ...widget.departments.map((dept) => DropdownMenuItem(
                      value: dept,
                      child: Text(dept),
                    )),
              ],
              onChanged: (value) => setState(() => _selectedDepartment = value),
            ),
            const SizedBox(height: 24),
          ],
          // Sort Options
          const Text('Sort By:', style: TextStyle(fontWeight: FontWeight.w600)),
          const SizedBox(height: 8),
          ...SortOption.values.map((option) => RadioListTile<SortOption>(
                title: Text(_getSortLabel(option)),
                value: option,
                groupValue: _sortOption,
                onChanged: (value) => setState(() => _sortOption = value),
              )),
          const SizedBox(height: 24),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () {
                    setState(() {
                      _selectedStatus = null;
                      _selectedDepartment = null;
                      _sortOption = null;
                    });
                  },
                  child: const Text('Clear All'),
                ),
              ),
              const SizedBox(width: 16),
              Expanded(
                flex: 2,
                child: FilledButton(
                  onPressed: () {
                    widget.onApply(_selectedStatus, _selectedDepartment, _sortOption);
                    Navigator.pop(context);
                  },
                  child: const Text('Apply'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  String _getSortLabel(SortOption option) {
    switch (option) {
      case SortOption.newest:
        return 'Newest First';
      case SortOption.oldest:
        return 'Oldest First';
      case SortOption.titleAsc:
        return 'Title (A-Z)';
      case SortOption.titleDesc:
        return 'Title (Z-A)';
      case SortOption.status:
        return 'By Status';
    }
  }
}

class _FilterChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onSelected;

  const _FilterChip({
    required this.label,
    required this.selected,
    required this.onSelected,
  });

  @override
  Widget build(BuildContext context) {
    return FilterChip(
      label: Text(label),
      selected: selected,
      onSelected: (_) => onSelected(),
    );
  }
}
