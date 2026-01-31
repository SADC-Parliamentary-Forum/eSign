import 'package:flutter/material.dart';
import '../screens/document_detail_screen.dart';

class DocumentSearchDelegate extends SearchDelegate<String?> {
  final List<dynamic> documents;

  DocumentSearchDelegate(this.documents);

  @override
  List<Widget>? buildActions(BuildContext context) {
    return [
      if (query.isNotEmpty)
        IconButton(
          icon: const Icon(Icons.clear),
          onPressed: () {
            query = '';
            showSuggestions(context);
          },
        ),
    ];
  }

  @override
  Widget? buildLeading(BuildContext context) {
    return IconButton(
      icon: const Icon(Icons.arrow_back),
      onPressed: () => close(context, null),
    );
  }

  @override
  Widget buildResults(BuildContext context) {
    return _buildList(context);
  }

  @override
  Widget buildSuggestions(BuildContext context) {
    return _buildList(context);
  }

  Widget _buildList(BuildContext context) {
    final cleanQuery = query.toLowerCase().trim();
    
    final results = documents.where((doc) {
      if (cleanQuery.isEmpty) return true;
      final title = (doc['title'] ?? '').toString().toLowerCase();
      final dept = (doc['department'] ?? '').toString().toLowerCase();
      final ref = (doc['reference_number'] ?? '').toString().toLowerCase();
      return title.contains(cleanQuery) || 
             dept.contains(cleanQuery) ||
             ref.contains(cleanQuery);
    }).toList();

    if (results.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.search_off, size: 64, color: Colors.grey[400]),
            const SizedBox(height: 16),
            Text(
              'No documents found matching "$query"',
              style: TextStyle(color: Colors.grey[600]),
            ),
          ],
        ),
      );
    }

    return ListView.builder(
      itemCount: results.length,
      itemBuilder: (context, index) {
        final doc = results[index];
        final title = doc['title'] ?? 'Untitled';
        final dept = doc['department'] ?? 'General';
        final status = doc['status'] ?? 'unknown';

        return ListTile(
          leading: const Icon(Icons.description),
          title: Text(title),
          subtitle: Text('$dept • ${status.toUpperCase()}'),
          onTap: () {
            close(context, null); // Close search
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => DocumentDetailScreen(documentId: doc['id'].toString()),
              ),
            );
          },
        );
      },
    );
  }
}
