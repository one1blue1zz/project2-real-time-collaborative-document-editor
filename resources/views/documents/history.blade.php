<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Version History - {{ $document->title }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-2xl font-bold mb-2">📜 Version History</h1>
        <p class="mb-4">Document: <strong>{{ $document->title }}</strong> | Current Version: <strong>{{ $document->current_version }}</strong></p>
        
        <a href="{{ route('documents.edit', $document->id) }}" class="text-blue-500">← Back to Edit</a>
        |
        <a href="{{ route('documents.index') }}" class="text-green-500">← Back to Documents</a>
        
        <div class="mt-6 space-y-4">
            @forelse($versions as $ver)
            <div class="border p-4 rounded shadow-sm">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold">Version {{ $ver['version'] }}</h3>
                    <span class="text-sm text-gray-500">{{ \Carbon\Carbon::parse($ver['created_at'])->diffForHumans() }}</span>
                </div>
                <p class="text-sm text-gray-600 mt-1">
                    <strong>Dibuat oleh:</strong> {{ $ver['user_name'] ?? 'System' }}
                </p>
                <div class="mt-2 p-2 bg-gray-50 rounded text-sm">
                    <strong>Content preview:</strong>
                    <p class="mt-1">{{ Str::limit($ver['content'], 200) }}</p>
                </div>
            </div>
            @empty
                <div class="border p-4 rounded shadow-sm">
                    <p class="text-gray-500 text-center">No version history available yet.</p>
                    <p class="text-gray-400 text-center text-sm mt-2">Versions will appear when users save the document.</p>
                </div>
            @endforelse
        </div>
        
        @if(!empty($versions))
        <div class="mt-4 text-sm text-gray-500 text-center">
            Total versions: {{ count($versions) }}
        </div>
        @endif
    </div>
</body>
</html>