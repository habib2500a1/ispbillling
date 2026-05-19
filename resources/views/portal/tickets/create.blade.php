@extends('portal.layout')

@section('title', 'New support ticket')

@section('content')
    <h1 class="mb-6 text-2xl font-semibold text-slate-900">New support ticket</h1>

    <form method="post" action="{{ route('portal.tickets.store') }}" enctype="multipart/form-data" class="max-w-2xl space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        <div>
            <label for="subject" class="mb-1 block text-sm font-medium text-slate-700">Subject</label>
            <input type="text" name="subject" id="subject" value="{{ old('subject') }}" required maxlength="255"
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>
        <div>
            <label for="department" class="mb-1 block text-sm font-medium text-slate-700">Department</label>
            <select name="department" id="department" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                @foreach ($departments as $value => $label)
                    <option value="{{ $value }}" @selected(old('department') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="priority" class="mb-1 block text-sm font-medium text-slate-700">Priority</label>
            <select name="priority" id="priority" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                @foreach ($priorities as $value => $label)
                    <option value="{{ $value }}" @selected(old('priority', 'medium') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="issue_type" class="mb-1 block text-sm font-medium text-slate-700">Issue type (optional)</label>
            <select name="issue_type" id="issue_type" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                <option value="">— Select —</option>
                @foreach ($issueTypes as $value => $label)
                    <option value="{{ $value }}" @selected(old('issue_type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="description" class="mb-1 block text-sm font-medium text-slate-700">Describe the problem</label>
            <textarea name="description" id="description" rows="6" required maxlength="10000"
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">{{ old('description') }}</textarea>
        </div>
        <div>
            <label for="attachment" class="mb-1 block text-sm font-medium text-slate-700">Photo / PDF (optional)</label>
            <input type="file" name="attachment" id="attachment" accept="image/*,.pdf"
                class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-md file:border-0 file:bg-amber-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-amber-800 hover:file:bg-amber-100">
        </div>
        <div class="flex gap-3 pt-2">
            <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">Submit ticket</button>
            <a href="{{ route('portal.tickets.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</a>
        </div>
    </form>
@endsection
