@extends('layouts.app')
@section('title', 'Reportes' . ($grupo ? ' de ' . $grupo : ''))
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach ($reportes as $k => [$t, $g, $d, $i])
            <a href="{{ route('reportes.ver', $k) }}" class="bg-white rounded-2xl shadow-sm p-5 flex gap-4 hover:shadow-md hover:-translate-y-0.5 transition">
                <span class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl shrink-0"><i class="fas {{ $i }}"></i></span>
                <span><span class="block font-bold text-gray-800">{{ $t }}</span><span class="block text-sm text-gray-500">{{ $d }}</span>
                    <span class="mt-2 inline-flex gap-1 text-[10px] font-bold"><span class="px-1.5 py-0.5 rounded bg-green-100 text-green-700">EXCEL</span><span class="px-1.5 py-0.5 rounded bg-rose-100 text-rose-700">PDF</span></span></span>
            </a>
        @endforeach
    </div>
@endsection
