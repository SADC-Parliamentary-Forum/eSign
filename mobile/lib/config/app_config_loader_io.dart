// Mobile/desktop: load from asset bundle.

import 'dart:convert';
import 'package:flutter/services.dart';

Future<Map<String, dynamic>> loadConfigJson() async {
  final s = await rootBundle.loadString('assets/config.json');
  return jsonDecode(s) as Map<String, dynamic>;
}
