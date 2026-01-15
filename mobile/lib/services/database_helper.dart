import 'package:sqflite/sqflite.dart';
import 'package:path/path.dart';

class DatabaseHelper {
  static final DatabaseHelper instance = DatabaseHelper._init();
  static Database? _database;

  DatabaseHelper._init();

  Future<Database> get database async {
    if (_database != null) return _database!;
    _database = await _initDB('esign.db');
    return _database!;
  }

  Future<Database> _initDB(String filePath) async {
    final dbPath = await getDatabasesPath();
    final path = join(dbPath, filePath);

    return await openDatabase(path, version: 1, onCreate: _createDB);
  }

  Future _createDB(Database db, int version) async {
    await db.execute('''
      CREATE TABLE documents (
        id TEXT PRIMARY KEY,
        title TEXT,
        department TEXT,
        status TEXT,
        file_path TEXT,
        created_at TEXT
      )
    ''');
  }

  Future<void> cacheDocuments(List<dynamic> documents) async {
    final db = await instance.database;
    final batch = db.batch();
    
    // Clear old cache (simple strategy)
    batch.delete('documents');

    for (var doc in documents) {
      batch.insert('documents', {
        'id': doc['id'],
        'title': doc['title'],
        'department': doc['department'],
        'status': doc['status'],
        'file_path': doc['file_path'],
        'created_at': doc['created_at'],
      });
    }

    await batch.commit(noResult: true);
  }

  Future<List<dynamic>> getCachedDocuments() async {
    final db = await instance.database;
    final result = await db.query('documents', orderBy: 'created_at DESC');
    return result;
  }
}
