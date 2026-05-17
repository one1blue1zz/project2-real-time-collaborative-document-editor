<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Version History - {{ $document->title }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Riwayat Versi</h1>
                    <p class="text-gray-600 mt-1">{{ $document->title }}</p>
                </div>
                <a href="{{ route('documents.edit', $document) }}" 
                   class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Kembali ke Dokumen
                </a>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="divide-y divide-gray-200">
                    @forelse($versions as $version)
                        <div class="p-6 hover:bg-gray-50">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <span class="text-lg font-semibold text-gray-900">
                                            Version {{ $version->version }}
                                        </span>
                                        @if($version->version == $document->version)
                                            <span class="ml-2 px-2 py-1 text-xs bg-green-100 text-green-800 rounded">
                                                Current
                                            </span>
                                        @endif
                                    </div>
                                    
                                    <div class="text-sm text-gray-600 mb-2">
                                        <span class="font-medium">By:</span> {{ $version->user_name }}
                                        <span class="mx-2">•</span>
                                        <span class="font-medium">Time:</span> {{ $version->created_at->format('Y-m-d H:i:s') }}
                                        <span class="mx-2">•</span>
                                        <span class="font-medium">Changes:</span>
                                        @if($version->changes)
                                            @if(isset($version->changes['action']))
                                                @if($version->changes['action'] == 'created')
                                                    Document created
                                                @elseif($version->changes['action'] == 'edited')
                                                    {{ $version->changes['difference'] > 0 ? '+' : '' }}{{ $version->changes['difference'] }} characters
                                                @elseif($version->changes['action'] == 'restored')
                                                    Restored from version {{ $version->changes['from_version'] }}
                                                @endif
                                            @endif
                                        @else
                                            No changes recorded
                                        @endif
                                    </div>
                                    
                                    <div class="bg-gray-50 rounded p-3 mt-2">
                                        <p class="text-sm text-gray-700 font-mono whitespace-pre-wrap line-clamp-3">
                                            {{ Str::limit($version->content, 200) }}
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="ml-4">
                                    @if($version->version != $document->version)
                                        <form action="{{ route('documents.restore', [$document, $version->version]) }}" 
                                              method="POST" 
                                              onsubmit="return confirm('Restore to version {{ $version->version }}? Current content will be saved as a new version.');">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" 
                                                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm">
                                                Restore
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-gray-500">
                            Tidak ada riwayat versi
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</body>
</html>