<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Buat Dokumen</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="p-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold mb-4">Buat Dokumen Baru</h1>
        
        <form action="{{ route('documents.store') }}" method="POST">
            @csrf
            
            <div class="mb-4">
                <label class="block font-bold mb-1">Judul</label>
                <input type="text" name="title" class="w-full border border-gray-300 p-2 rounded" required>
            </div>
            
            <div class="mb-4">
                <label class="block font-bold mb-1">Konten</label>
                <textarea name="content" rows="10" class="w-full border border-gray-300 p-2 rounded"></textarea>
            </div>
            
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Simpan</button>
            <a href="{{ route('documents.index') }}" class="bg-gray-500 text-white px-4 py-2 rounded inline-block text-center">Batal</a>
        </form>
    </div>
</body>
</html>