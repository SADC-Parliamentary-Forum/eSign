import 'dart:async';
import 'package:connectivity_plus/connectivity_plus.dart';

class ConnectivityService {
  // Singleton instance
  static final ConnectivityService _instance = ConnectivityService._internal();

  factory ConnectivityService() {
    return _instance;
  }

  ConnectivityService._internal() {
    // Initial check
    _initConnectivity();
    
    // Listen for changes
    _connectivity.onConnectivityChanged.listen(_updateConnectionStatus);
  }

  final Connectivity _connectivity = Connectivity();
  final StreamController<bool> _connectionStatusController = StreamController<bool>.broadcast();

  // Expose stream of online status (true = online, false = offline)
  Stream<bool> get isOnline => _connectionStatusController.stream;

  // Current status
  bool _hasConnection = true;
  bool get hasConnection => _hasConnection;

  Future<void> _initConnectivity() async {
    try {
      final results = await _connectivity.checkConnectivity();
      _updateConnectionStatus(results);
    } catch (e) {
      print('Could not check connectivity status: $e');
    }
  }

  void _updateConnectionStatus(List<ConnectivityResult> results) {
    // If any of the results is mobile or wifi, we are "connected" to a network.
    // Note: This doesn't guarantee internet access, just network connection.
    // For strict internet checks, one might ping a server, but for UX "Offline" banner, this is usually sufficient.
    final isConnected = results.any((result) => 
      result == ConnectivityResult.mobile || 
      result == ConnectivityResult.wifi || 
      result == ConnectivityResult.ethernet || 
      result == ConnectivityResult.vpn
    );

    if (_hasConnection != isConnected) {
      _hasConnection = isConnected;
      _connectionStatusController.add(_hasConnection);
    }
  }

  void dispose() {
    _connectionStatusController.close();
  }
}
