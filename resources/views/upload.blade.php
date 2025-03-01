<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Upload</title>
</head>
<body>
    <h2>Upload Image</h2>

    @if ($message = session('success'))
        <div style="color: green;">{{ $message }}</div>
        <img src="{{ asset('storage/images/' . session('image')) }}" alt="Uploaded Image" width="300">
    @elseif ($message = session('error'))
        <div style="color: red;">{{ $message }}</div>
    @endif

    <form action="{{ url('upload') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="file" name="image" required>
        <button type="submit">Upload</button>
    </form>
</body>
</html>
