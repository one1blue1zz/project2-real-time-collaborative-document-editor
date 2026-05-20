<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Documents</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="p-8">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-2xl font-bold mb-4">My Documents</h1>
        
        @if(session('success'))
            <div class="bg-green-100 text-green-700 p-3 mb-4 rounded">{{ session('success') }}</div>
        @endif
        
        <a href="{{ route('documents.create') }}" class="bg-blue-500 text-white px-4 py-2 rounded inline-block mb-4">+ Create New Document</a>
        
        <table class="w-full border-collapse border border-gray-300">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border p-2">TITLE</th>
                    <th class="border p-2">VERSION</th>
                    <th class="border p-2">LAST UPDATED</th>
                    <th class="border p-2">STATUS</th>
                    <th class="border p-2">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                @foreach($documents as $doc)
                <tr>
                    <td class="border p-2">{{ $doc->title }}</td>
                    <td class="border p-2 text-center">Versi {{ $doc->current_version }}</td>
                    <td class="border p-2">{{ $doc->updated_at->diffForHumans() }}</td>
                    <td class="border p-2 text-center">
                        <span class="text-green-600">✅ Available</span>
                    </td>
                    <td class="border p-2 text-center">
                        <a href="{{ route('documents.edit', $doc->id) }}" class="text-blue-500">Edit</a> |
                        <a href="{{ route('documents.history', $doc->id) }}" class="text-green-500">History</a> |
                        <form action="{{ route('documents.destroy', $doc->id) }}" method="POST" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-500" onclick="return confirm('Delete?')">Delete</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>