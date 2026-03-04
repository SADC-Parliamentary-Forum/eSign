import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../config/app_config.dart';

class ApiService {
  static String get baseUrl => AppConfig.instance.apiBaseUrl;
  
  static Future<String?> getToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('token');
  }

  static Future<Map<String, String>> _getHeaders({bool includeAuth = true}) async {
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    
    if (includeAuth) {
      final token = await getToken();
      if (token != null) {
        headers['Authorization'] = 'Bearer $token';
      }
    }
    
    return headers;
  }

  static Future<Map<String, dynamic>?> _handleResponse(http.Response response) async {
    if (response.statusCode >= 200 && response.statusCode < 300) {
      if (response.body.isEmpty) return {'success': true};
      return jsonDecode(response.body) as Map<String, dynamic>;
    } else if (response.statusCode == 401) {
      // Unauthorized - clear token
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('token');
      throw Exception('Unauthorized. Please login again.');
    } else {
      final error = jsonDecode(response.body);
      throw Exception(error['message'] ?? 'Request failed with status ${response.statusCode}');
    }
  }

  // ============================================================================
  // Authentication
  // ============================================================================
  static Future<Map<String, dynamic>?> login(String email, String password) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/auth/login'),
        headers: await _getHeaders(includeAuth: false),
        body: jsonEncode({'email': email, 'password': password}),
      );

      final data = await _handleResponse(response);
      if (data != null && data['access_token'] != null) {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('token', data['access_token']);
        if (data['user'] != null) {
          await prefs.setString('user', jsonEncode(data['user']));
        }
      }
      return data;
    } catch (e) {
      print('Login Error: $e');
      rethrow;
    }
  }

  static Future<Map<String, dynamic>?> register(Map<String, dynamic> data) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/auth/register'),
        headers: await _getHeaders(includeAuth: false),
        body: jsonEncode(data),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Register Error: $e');
      rethrow;
    }
  }

  static Future<void> logout() async {
    try {
      await http.post(
        Uri.parse('$baseUrl/auth/logout'),
        headers: await _getHeaders(),
      );
    } catch (e) {
      print('Logout Error: $e');
    } finally {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('token');
      await prefs.remove('user');
    }
  }

  static Future<Map<String, dynamic>?> getCurrentUser() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/auth/me'),
        headers: await _getHeaders(),
      );
      final data = await _handleResponse(response);
      if (data != null) {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('user', jsonEncode(data));
      }
      return data;
    } catch (e) {
      print('Get User Error: $e');
      rethrow;
    }
  }

  static Future<Map<String, dynamic>?> updateProfile(Map<String, dynamic> data) async {
    try {
      final response = await http.put(
        Uri.parse('$baseUrl/auth/profile'),
        headers: await _getHeaders(),
        body: jsonEncode(data),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Update Profile Error: $e');
      rethrow;
    }
  }

  static Future<Map<String, dynamic>?> updatePassword(String currentPassword, String newPassword) async {
    try {
      final response = await http.put(
        Uri.parse('$baseUrl/auth/password'),
        headers: await _getHeaders(),
        body: jsonEncode({
          'current_password': currentPassword,
          'password': newPassword,
          'password_confirmation': newPassword,
        }),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Update Password Error: $e');
      rethrow;
    }
  }

  static Future<Map<String, dynamic>?> forgotPassword(String email) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/auth/forgot-password'),
        headers: await _getHeaders(includeAuth: false),
        body: jsonEncode({'email': email}),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Forgot Password Error: $e');
      rethrow;
    }
  }

  static Future<Map<String, dynamic>?> resetPassword(String email, String token, String password) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/auth/reset-password'),
        headers: await _getHeaders(includeAuth: false),
        body: jsonEncode({
          'email': email,
          'token': token,
          'password': password,
          'password_confirmation': password,
        }),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Reset Password Error: $e');
      rethrow;
    }
  }

  static Future<Map<String, dynamic>?> sendMfaCode() async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/auth/mfa/send'),
        headers: await _getHeaders(),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Send MFA Error: $e');
      rethrow;
    }
  }

  static Future<Map<String, dynamic>?> verifyMfa(String code) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/auth/mfa/verify'),
        headers: await _getHeaders(),
        body: jsonEncode({'code': code}),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Verify MFA Error: $e');
      rethrow;
    }
  }

  static Future<Map<String, dynamic>?> generateMagicLink() async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/auth/magic/generate'),
        headers: await _getHeaders(),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Generate Magic Link Error: $e');
      rethrow;
    }
  }

  static Future<bool> verifyEmail(String id, String hash, String expires, String signature) async {
    try {
      final uri = Uri.parse('$baseUrl/verification/email/verify/$id/$hash').replace(queryParameters: {
        'expires': expires,
        'signature': signature,
      });

      final response = await http.get(
        uri,
        headers: await _getHeaders(includeAuth: false),
      );

      return response.statusCode == 200;
    } catch (e) {
      print('Verification Error: $e');
      return false;
    }
  }

  // ============================================================================
  // Documents
  // ============================================================================
  static Future<List<dynamic>> getDocuments({Map<String, String>? queryParams}) async {
    try {
      var uri = Uri.parse('$baseUrl/documents');
      if (queryParams != null) {
        uri = uri.replace(queryParameters: queryParams);
      }
      
      final response = await http.get(uri, headers: await _getHeaders());
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data is List ? data : (data['data'] as List? ?? []);
      }
      return [];
    } catch (e) {
      print('Get Documents Error: $e');
      return [];
    }
  }

  static Future<Map<String, dynamic>?> getDocument(String id) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/documents/$id'),
        headers: await _getHeaders(),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Get Document Error: $e');
      rethrow;
    }
  }

  static Future<Map<String, dynamic>?> uploadDocument(File file, Map<String, dynamic> metadata) async {
    try {
      final request = http.MultipartRequest(
        'POST',
        Uri.parse('$baseUrl/documents'),
      );
      
      final token = await getToken();
      if (token != null) {
        request.headers['Authorization'] = 'Bearer $token';
      }
      request.headers['Accept'] = 'application/json';
      
      request.files.add(await http.MultipartFile.fromPath('file', file.path));
      metadata.forEach((key, value) {
        request.fields[key] = value.toString();
      });
      
      final streamedResponse = await request.send();
      final response = await http.Response.fromStream(streamedResponse);
      return await _handleResponse(response);
    } catch (e) {
      print('Upload Document Error: $e');
      rethrow;
    }
  }

  static Future<bool> deleteDocument(String id) async {
    try {
      final response = await http.delete(
        Uri.parse('$baseUrl/documents/$id'),
        headers: await _getHeaders(),
      );
      return response.statusCode == 200 || response.statusCode == 204;
    } catch (e) {
      print('Delete Document Error: $e');
      return false;
    }
  }

  static Future<Map<String, dynamic>?> signDocument(String id, String signatureData, {String? signatureId}) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/documents/$id/sign'),
        headers: await _getHeaders(),
        body: jsonEncode({
          'signature_data': signatureData,
          if (signatureId != null) 'signature_id': signatureId,
        }),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Sign Document Error: $e');
      rethrow;
    }
  }

  static Future<Map<String, dynamic>?> rejectDocument(String id, String reason) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/documents/$id/reject'),
        headers: await _getHeaders(),
        body: jsonEncode({'reason': reason}),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Reject Document Error: $e');
      rethrow;
    }
  }

  static Future<List<dynamic>> getPendingDocuments() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/documents/pending'),
        headers: await _getHeaders(),
      );
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data is List ? data : (data['data'] as List? ?? []);
      }
      return [];
    } catch (e) {
      print('Get Pending Documents Error: $e');
      return [];
    }
  }

  static Future<Map<String, dynamic>?> getDocumentStats() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/documents/stats'),
        headers: await _getHeaders(),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Get Document Stats Error: $e');
      rethrow;
    }
  }

  static Future<List<dynamic>> getDocumentActivity({String? documentId}) async {
    try {
      var uri = Uri.parse('$baseUrl/documents/activity');
      if (documentId != null) {
        uri = uri.replace(queryParameters: {'document_id': documentId});
      }
      
      final response = await http.get(uri, headers: await _getHeaders());
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data is List ? data : (data['data'] as List? ?? []);
      }
      return [];
    } catch (e) {
      print('Get Document Activity Error: $e');
      return [];
    }
  }

  static Future<Map<String, dynamic>?> addSigners(String documentId, List<Map<String, dynamic>> signers) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/documents/$documentId/signers'),
        headers: await _getHeaders(),
        body: jsonEncode({'signers': signers}),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Add Signers Error: $e');
      rethrow;
    }
  }

  static Future<Map<String, dynamic>?> sendDocument(String documentId) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/documents/$documentId/send'),
        headers: await _getHeaders(),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Send Document Error: $e');
      rethrow;
    }
  }

  static Future<Map<String, dynamic>?> getDocumentStatus(String documentId) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/documents/$documentId/status'),
        headers: await _getHeaders(),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Get Document Status Error: $e');
      rethrow;
    }
  }

  static Future<Uint8List?> downloadDocumentPdf(String documentId) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/documents/$documentId/pdf'),
        headers: await _getHeaders(),
      );
      if (response.statusCode == 200) {
        return response.bodyBytes;
      }
      return null;
    } catch (e) {
      print('Download PDF Error: $e');
      return null;
    }
  }

  static Future<Map<String, dynamic>?> bulkDeleteDocuments(List<String> documentIds) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/documents/bulk-delete'),
        headers: await _getHeaders(),
        body: jsonEncode({'ids': documentIds}),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Bulk Delete Error: $e');
      rethrow;
    }
  }

  static Future<Map<String, dynamic>?> bulkSignDocuments(List<String> documentIds, String signatureData) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/documents/bulk-sign'),
        headers: await _getHeaders(),
        body: jsonEncode({
          'ids': documentIds,
          'signature_data': signatureData,
        }),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Bulk Sign Error: $e');
      rethrow;
    }
  }

  // ============================================================================
  // Templates
  // ============================================================================
  static Future<List<dynamic>> getTemplates({Map<String, String>? queryParams}) async {
    try {
      var uri = Uri.parse('$baseUrl/templates');
      if (queryParams != null) {
        uri = uri.replace(queryParameters: queryParams);
      }
      
      final response = await http.get(uri, headers: await _getHeaders());
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data is List ? data : (data['data'] as List? ?? []);
      }
      return [];
    } catch (e) {
      print('Get Templates Error: $e');
      return [];
    }
  }

  static Future<Map<String, dynamic>?> getTemplate(String id) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/templates/$id'),
        headers: await _getHeaders(),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Get Template Error: $e');
      rethrow;
    }
  }

  static Future<Map<String, dynamic>?> createTemplate(Map<String, dynamic> data) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/templates'),
        headers: await _getHeaders(),
        body: jsonEncode(data),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Create Template Error: $e');
      rethrow;
    }
  }

  static Future<Map<String, dynamic>?> applyTemplate(String templateId, Map<String, dynamic> data) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/templates/$templateId/apply'),
        headers: await _getHeaders(),
        body: jsonEncode(data),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Apply Template Error: $e');
      rethrow;
    }
  }

  static Future<List<String>> getTemplateCategories() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/templates/meta/categories'),
        headers: await _getHeaders(),
      );
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return List<String>.from(data is List ? data : (data['categories'] ?? []));
      }
      return [];
    } catch (e) {
      print('Get Template Categories Error: $e');
      return [];
    }
  }

  // ============================================================================
  // Workflows
  // ============================================================================
  static Future<Map<String, dynamic>?> getWorkflow(String id) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/workflows/$id'),
        headers: await _getHeaders(),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Get Workflow Error: $e');
      rethrow;
    }
  }

  static Future<List<dynamic>> getWorkflowSteps(String id) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/workflows/$id/steps'),
        headers: await _getHeaders(),
      );
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data is List ? data : (data['data'] as List? ?? []);
      }
      return [];
    } catch (e) {
      print('Get Workflow Steps Error: $e');
      return [];
    }
  }

  static Future<List<dynamic>> getUserPendingWorkflows() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/workflows/user/pending'),
        headers: await _getHeaders(),
      );
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data is List ? data : (data['data'] as List? ?? []);
      }
      return [];
    } catch (e) {
      print('Get User Pending Workflows Error: $e');
      return [];
    }
  }

  static Future<Map<String, dynamic>?> getDocumentWorkflow(String documentId) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/documents/$documentId/workflow'),
        headers: await _getHeaders(),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Get Document Workflow Error: $e');
      rethrow;
    }
  }

  // ============================================================================
  // User Signatures
  // ============================================================================
  static Future<List<dynamic>> getUserSignatures() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/signatures/mine'),
        headers: await _getHeaders(),
      );
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data is List ? data : (data['data'] as List? ?? []);
      }
      return [];
    } catch (e) {
      print('Get User Signatures Error: $e');
      return [];
    }
  }

  static Future<Map<String, dynamic>?> createUserSignature(String signatureData, {String? name}) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/signatures/mine'),
        headers: await _getHeaders(),
        body: jsonEncode({
          'signature_data': signatureData,
          if (name != null) 'name': name,
        }),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Create User Signature Error: $e');
      rethrow;
    }
  }

  static Future<bool> setDefaultSignature(String signatureId) async {
    try {
      final response = await http.patch(
        Uri.parse('$baseUrl/signatures/mine/$signatureId/default'),
        headers: await _getHeaders(),
      );
      return response.statusCode == 200;
    } catch (e) {
      print('Set Default Signature Error: $e');
      return false;
    }
  }

  static Future<bool> deleteUserSignature(String signatureId) async {
    try {
      final response = await http.delete(
        Uri.parse('$baseUrl/signatures/mine/$signatureId'),
        headers: await _getHeaders(),
      );
      return response.statusCode == 200 || response.statusCode == 204;
    } catch (e) {
      print('Delete User Signature Error: $e');
      return false;
    }
  }

  // ============================================================================
  // Notifications
  // ============================================================================
  static Future<List<dynamic>> getNotifications() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/notifications'),
        headers: await _getHeaders(),
      );
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data is List ? data : (data['data'] as List? ?? []);
      }
      return [];
    } catch (e) {
      print('Get Notifications Error: $e');
      return [];
    }
  }

  static Future<bool> markNotificationAsRead(String notificationId) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/notifications/$notificationId/read'),
        headers: await _getHeaders(),
      );
      return response.statusCode == 200;
    } catch (e) {
      print('Mark Notification Read Error: $e');
      return false;
    }
  }

  static Future<bool> markAllNotificationsAsRead() async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/notifications/read-all'),
        headers: await _getHeaders(),
      );
      return response.statusCode == 200;
    } catch (e) {
      print('Mark All Notifications Read Error: $e');
      return false;
    }
  }

  // ============================================================================
  // Guest Signer (Token-based)
  // ============================================================================
  static Future<Map<String, dynamic>?> getSignerDocument(String token) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/sign/$token'),
        headers: await _getHeaders(includeAuth: false),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Get Signer Document Error: $e');
      rethrow;
    }
  }

  static Future<Map<String, dynamic>?> signAsGuest(
    String token,
    String signatureData, {
    String? amountInWords,
  }) async {
    try {
      final body = <String, dynamic>{'signature_data': signatureData};
      if (amountInWords != null && amountInWords.isNotEmpty) {
        body['amount_in_words'] = amountInWords;
      }
      final response = await http.post(
        Uri.parse('$baseUrl/sign/$token/sign'),
        headers: await _getHeaders(includeAuth: false),
        body: jsonEncode(body),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Sign As Guest Error: $e');
      rethrow;
    }
  }

  static Future<Map<String, dynamic>?> declineAsGuest(String token, String? reason) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/sign/$token/decline'),
        headers: await _getHeaders(includeAuth: false),
        body: jsonEncode({'reason': reason ?? 'No reason provided'}),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Decline As Guest Error: $e');
      rethrow;
    }
  }

  // ============================================================================
  // Amount Verification
  // ============================================================================
  /// Returns canonical word form for a plain numeric amount.
  /// Calls GET /api/amount/{numeric}/words
  static Future<Map<String, dynamic>> getAmountInWords(double amount) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/amount/$amount/words'),
        headers: await _getHeaders(),
      );
      return (await _handleResponse(response)) ?? {};
    } catch (e) {
      print('Get Amount In Words Error: $e');
      rethrow;
    }
  }

  /// Extracts the amount from a document's PDF and returns the word form.
  /// Calls GET /api/documents/{id}/amount-words
  static Future<Map<String, dynamic>> getDocumentAmountWords(String documentId) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/documents/$documentId/amount-words'),
        headers: await _getHeaders(),
      );
      return (await _handleResponse(response)) ?? {};
    } catch (e) {
      print('Get Document Amount Words Error: $e');
      rethrow;
    }
  }

  // ============================================================================
  // Verification
  // ============================================================================
  static Future<Map<String, dynamic>?> createEmailVerification(String signerId) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/verification/signers/$signerId/email'),
        headers: await _getHeaders(),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Create Email Verification Error: $e');
      rethrow;
    }
  }

  static Future<Map<String, dynamic>?> createOTPVerification(String signerId) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/verification/signers/$signerId/otp'),
        headers: await _getHeaders(),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Create OTP Verification Error: $e');
      rethrow;
    }
  }

  static Future<Map<String, dynamic>?> verifyOTP(String signerId, String otp) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/verification/signers/$signerId/otp/verify'),
        headers: await _getHeaders(),
        body: jsonEncode({'otp': otp}),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Verify OTP Error: $e');
      rethrow;
    }
  }

  static Future<Map<String, dynamic>?> getVerificationStatus(String signerId) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/verification/signers/$signerId/status'),
        headers: await _getHeaders(),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Get Verification Status Error: $e');
      rethrow;
    }
  }

  // ============================================================================
  // Folders
  // ============================================================================
  static Future<List<dynamic>> getFolders() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/folders'),
        headers: await _getHeaders(),
      );
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data is List ? data : (data['data'] as List? ?? []);
      }
      return [];
    } catch (e) {
      print('Get Folders Error: $e');
      return [];
    }
  }

  static Future<Map<String, dynamic>?> createFolder(Map<String, dynamic> data) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/folders'),
        headers: await _getHeaders(),
        body: jsonEncode(data),
      );
      return await _handleResponse(response);
    } catch (e) {
      print('Create Folder Error: $e');
      rethrow;
    }
  }

  // ============================================================================
  // Departments
  // ============================================================================
  static Future<List<dynamic>> getDepartments() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/departments'),
        headers: await _getHeaders(),
      );
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data is List ? data : (data['data'] as List? ?? []);
      }
      return [];
    } catch (e) {
      print('Get Departments Error: $e');
      return [];
    }
  }

  // ============================================================================
  // Delegations
  // ============================================================================
  static Future<List<dynamic>> getDelegations() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/delegations'),
        headers: await _getHeaders(),
      );
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data is List ? data : (data['data'] as List? ?? []);
      }
      return [];
    } catch (e) {
      print('Get Delegations Error: $e');
      return [];
    }
  }
}
