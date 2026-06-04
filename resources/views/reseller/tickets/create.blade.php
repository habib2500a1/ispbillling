@extends('reseller.layout')

@section('title', 'New ticket')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'New support ticket',
        'backUrl' => route('reseller.tickets.index'),
        'backLabel' => '← Tickets',
    ])

    <div class="rsl-panel rsl-panel-pad" style="max-width:36rem">
        <form method="post" action="{{ route('reseller.tickets.store') }}" class="rsl-form-grid">
            @csrf
            <div class="rsl-field">
                <label class="rsl-field-label" for="customer_id">Subscribers</label>
                <select id="customer_id" name="customer_id" required class="rsl-input">
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}">{{ $c->customer_code }} — {{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="rsl-field">
                <label class="rsl-field-label" for="subject">Subject</label>
                <input id="subject" name="subject" required class="rsl-input">
            </div>
            <div class="rsl-field">
                <label class="rsl-field-label" for="description">Description</label>
                <textarea id="description" name="description" required rows="5" class="rsl-input"></textarea>
            </div>
            <div class="rsl-form-grid rsl-form-grid--2">
                <div class="rsl-field">
                    <label class="rsl-field-label" for="department">Department</label>
                    <select id="department" name="department" class="rsl-input">@foreach ($departments as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                </div>
                <div class="rsl-field">
                    <label class="rsl-field-label" for="priority">Priority</label>
                    <select id="priority" name="priority" class="rsl-input">@foreach ($priorities as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                </div>
            </div>
            <div class="rsl-field">
                <label class="rsl-field-label" for="issue_type">Issue type</label>
                <select id="issue_type" name="issue_type" class="rsl-input"><option value="">—</option>@foreach ($issueTypes as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
            </div>
            <button type="submit" class="rsl-btn">Submit</button>
        </form>
    </div>
@endsection
