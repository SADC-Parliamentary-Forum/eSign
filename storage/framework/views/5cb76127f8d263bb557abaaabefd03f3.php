<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Combined Audit Trail</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #1a73e8;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #1a73e8;
            margin: 0 0 5px 0;
            font-size: 18pt;
        }

        .header .subtitle {
            color: #666;
            font-size: 10pt;
        }

        .summary-box {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
        }

        .summary-box h2 {
            margin: 0 0 10px 0;
            font-size: 12pt;
            color: #1a73e8;
        }

        .summary-grid {
            display: table;
            width: 100%;
        }

        .summary-item {
            display: table-row;
        }

        .summary-label {
            display: table-cell;
            padding: 4px 10px 4px 0;
            font-weight: bold;
            width: 150px;
            color: #555;
        }

        .summary-value {
            display: table-cell;
            padding: 4px 0;
        }

        .document-section {
            page-break-inside: avoid;
            margin-bottom: 25px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }

        .document-header {
            background: #1a73e8;
            color: white;
            padding: 12px 15px;
        }

        .document-header h3 {
            margin: 0;
            font-size: 11pt;
        }

        .document-header .doc-status {
            font-size: 9pt;
            opacity: 0.9;
        }

        .document-body {
            padding: 15px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .info-table th,
        .info-table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 9pt;
        }

        .info-table th {
            background: #f5f5f5;
            font-weight: bold;
            color: #555;
            width: 30%;
        }

        .signers-section h4,
        .events-section h4 {
            color: #1a73e8;
            font-size: 10pt;
            margin: 15px 0 10px 0;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 5px;
        }

        .signer-table,
        .events-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }

        .signer-table th,
        .signer-table td,
        .events-table th,
        .events-table td {
            padding: 6px 8px;
            text-align: left;
            border: 1px solid #e0e0e0;
        }

        .signer-table th,
        .events-table th {
            background: #f8f9fa;
            font-weight: bold;
        }

        .status-signed {
            color: #0d6e0d;
            font-weight: bold;
        }

        .status-pending {
            color: #b86e00;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            font-size: 8pt;
            color: #888;
        }

        .page-break {
            page-break-after: always;
        }

        .hash-value {
            font-family: 'Courier New', monospace;
            font-size: 7pt;
            word-break: break-all;
            background: #f5f5f5;
            padding: 2px 4px;
            border-radius: 3px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Combined Audit Trail</h1>
        <div class="subtitle">SADC Parliamentary Forum eSign Platform</div>
    </div>

    <div class="summary-box">
        <h2>Bundle Summary</h2>
        <div class="summary-grid">
            <div class="summary-item">
                <span class="summary-label">Generated At:</span>
                <span class="summary-value"><?php echo e($generatedAt->format('F j, Y \a\t g:i A T')); ?></span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Total Documents:</span>
                <span class="summary-value"><?php echo e($auditData['total_documents']); ?></span>
            </div>
            <div class="summary-item">
                <span class="summary-label">All Completed:</span>
                <span class="summary-value">Yes</span>
            </div>
        </div>
    </div>

    <?php $__currentLoopData = $documents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $document): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="document-section">
            <div class="document-header">
                <h3><?php echo e($index + 1); ?>. <?php echo e($document->title); ?></h3>
                <div class="doc-status">Status: <?php echo e($document->status); ?></div>
            </div>

            <div class="document-body">
                <table class="info-table">
                    <tr>
                        <th>Document ID</th>
                        <td><?php echo e($document->id); ?></td>
                    </tr>
                    <tr>
                        <th>Created</th>
                        <td><?php echo e($document->created_at->format('M j, Y \a\t g:i A')); ?></td>
                    </tr>
                    <tr>
                        <th>Completed</th>
                        <td><?php echo e($document->completed_at ? $document->completed_at->format('M j, Y \a\t g:i A') : 'N/A'); ?>

                        </td>
                    </tr>
                    <tr>
                        <th>Signature Level</th>
                        <td><?php echo e($document->signature_level ?? 'Standard'); ?></td>
                    </tr>
                    <tr>
                        <th>File Hash (SHA-256)</th>
                        <td><span class="hash-value"><?php echo e($document->file_hash ?? 'Not available'); ?></span></td>
                    </tr>
                </table>

                <?php if($document->signers && $document->signers->count() > 0): ?>
                    <div class="signers-section">
                        <h4>Signers (<?php echo e($document->signers->count()); ?>)</h4>
                        <table class="signer-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Signed At</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $__currentLoopData = $document->signers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $signer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr>
                                        <td><?php echo e($signer->name); ?></td>
                                        <td><?php echo e($signer->email); ?></td>
                                        <td class="<?php echo e($signer->status === 'signed' ? 'status-signed' : 'status-pending'); ?>">
                                            <?php echo e(ucfirst($signer->status ?? 'pending')); ?>

                                        </td>
                                        <td><?php echo e($signer->signed_at ? \Carbon\Carbon::parse($signer->signed_at)->format('M j, Y g:i A') : '-'); ?>

                                        </td>
                                        <td><?php echo e($signer->ip_address ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if($document->workflowLogs && $document->workflowLogs->count() > 0): ?>
                    <div class="events-section">
                        <h4>Event Log</h4>
                        <table class="events-table">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Action</th>
                                    <th>Actor</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $__currentLoopData = $document->workflowLogs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr>
                                        <td><?php echo e($log->created_at->format('M j, Y g:i A')); ?></td>
                                        <td><?php echo e($log->action); ?></td>
                                        <td><?php echo e($log->actor ?? '-'); ?></td>
                                        <td><?php echo e($log->ip_address ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if(!$loop->last): ?>
            <div style="margin-bottom: 20px;"></div>
        <?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

    <div class="footer">
        <p>This document was automatically generated by SADC Parliamentary Forum eSign Platform.</p>
        <p>Generated: <?php echo e($generatedAt->format('Y-m-d H:i:s T')); ?> | Documents: <?php echo e($documents->count()); ?></p>
        <p><strong>This audit trail serves as a legal record of all signing activities.</strong></p>
    </div>
</body>

</html><?php /**PATH /var/www/html/resources/views/pdf/combined-audit-trail.blade.php ENDPATH**/ ?>