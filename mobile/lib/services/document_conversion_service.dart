import 'dart:io';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:path_provider/path_provider.dart';

class DocumentConversionService {
  /// Converts a list of image files to a single PDF document.
  /// Returns the File object of the generated PDF.
  static Future<File> createPdfFromImages(List<File> images) async {
    final pdf = pw.Document();

    for (var imageFile in images) {
      final image = pw.MemoryImage(
        await imageFile.readAsBytes(),
      );

      pdf.addPage(
        pw.Page(
          pageFormat: PdfPageFormat.a4,
          build: (pw.Context context) {
            return pw.Center(
              child: pw.Image(image),
            );
          },
        ),
      );
    }

    final outputDir = await getTemporaryDirectory();
    final file = File('${outputDir.path}/scanned_document_${DateTime.now().millisecondsSinceEpoch}.pdf');
    await file.writeAsBytes(await pdf.save());

    return file;
  }
}
