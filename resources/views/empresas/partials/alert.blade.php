@if (session('success'))
    <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-2 text-sm">
        {{ session('success') }}
    </div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-2 text-sm">
        <ul class="list-disc list-inside">
            @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
        </ul>
    </div>
@endif