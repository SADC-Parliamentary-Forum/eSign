<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Audit Trail - {{ $document->title }}</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
            color: #333;
        }

        .header {
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #1976D2;
        }

        .meta {
            margin-bottom: 20px;
        }

        .meta-table {
            width: 100%;
        }

        .meta-table td {
            padding: 5px;
        }

        .label {
            font-weight: bold;
            color: #666;
            width: 150px;
        }

        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin-top: 30px;
            margin-bottom: 10px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th {
            text-align: left;
            background-color: #f5f5f5;
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }

        td {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }

        .status-signed {
            color: green;
            font-weight: bold;
        }

        .status-pending {
            color: orange;
        }

        .status-declined {
            color: red;
        }

        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            font-size: 10px;
            color: #999;
            text-align: center;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }

        .hash {
            font-family: monospace;
            font-size: 10px;
            word-break: break-all;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="logo">SADC eSign</div>
        <div>Audit Trail & Certificate of Completion</div>
    </div>

    <div class="meta">
        <table class="meta-table">
            <tr>
                <td class="label">Document Title:</td>
                <td>{{ $document->title }}</td>
            </tr>
            <tr>
                <td class="label">Document ID:</td>
                <td>{{ $document->id }}</td>
            </tr>
            <tr>
                <td class="label">Created By:</td>
                <td>{{ $document->user->name }} ({{ $document->user->email }})</td>
            </tr>
            <tr>
                <td class="label">Created Date:</td>
                <td>{{ $document->created_at->format('Y-m-d H:i:s UTC') }}</td>
            </tr>
            <tr>
                <td class="label">Status:</td>
                <td>{{ $document->status }}</td>
            </tr>
            <tr>
                <td class="label">Document Hash (SHA256):</td>
                <td class="hash">{{ $document->file_hash }}</td>
            </tr>
        </table>
    </div>

    <div class="section-title">Signer Events</div>
    <table>
        <thead>
            <tr>
                <th>Signer</th>
                <th>Action</th>
                <th>Date (UTC)</th>
                <th>IP Address</th>
                <th>Signature ID</th>
            </tr>
        </thead>
        <tbody>
            @foreach($document->signers as $signer)
                @if($signer->signed_at || $signer->declined_at)
                    <tr>
                        <td>
                            <strong>{{ $signer->name }}</strong><br>
                            {{ $signer->email }}<br>
                            <span style="font-size:10px; color:#666">{{ $signer->role ?? 'Signer' }}</span>
                        </td>
                        <td>
                            @if($signer->signed_at)
                                <span class="status-signed">SIGNED</span>
                            @elseif($signer->declined_at)
                                <span class="status-declined">DECLINED</span>
                            @endif
                        </td>
                        <td>
                            {{ $signer->signed_at ? $signer->signed_at->format('Y-m-d H:i:s') : $signer->declined_at->format('Y-m-d H:i:s') }}
                        </td>
                        <td>{{ $signer->ip_address ?? 'N/A' }}</td>
                        <td class="hash">{{ $signer->signature_id ?? 'N/A' }}</td>
                    </tr>
                @endif
            @endforeach
            @if($document->signers->whereNull('signed_at')->whereNull('declined_at')->count() > 0)
                <tr>
                    <td colspan="5" style="text-align:center; color:#999; font-style:italic;">
                        Pending Signers:
                        {{ $document->signers->whereNull('signed_at')->whereNull('declined_at')->pluck('name')->join(', ') }}
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="section-title">Workflow History</div>
    <table>
        <thead>
            <tr>
                <th>Time (UTC)</th>
                <th>Action</th>
                <th>User</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            @foreach($document->workflowLogs as $log)
                <tr>
                    <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->user_name ?? 'System' }}</td>
                    <td>{{ $log->details }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Generated by SADC eSign Platform on {{ now()->format('Y-m-d H:i:s UTC') }}<br>
        Document ID: {{ $document->id }}
    </div>
</body>

</html>