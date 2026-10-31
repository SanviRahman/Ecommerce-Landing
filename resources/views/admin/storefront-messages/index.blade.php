@extends('adminlte::page')
@section('title', 'Contact Messages')
@section('content_header')<div class="d-flex justify-content-between align-items-center"><h1>Contact Messages <small class="text-muted h6">{{ $newCount }} new</small></h1><a class="btn btn-outline-secondary" href="{{ route('admin.site-settings.index') }}">Storefront Contact Settings</a></div>@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="card shadow-sm"><div class="card-header bg-white"><form method="GET" class="form-inline"><label class="mr-2" for="status">Status</label><select class="form-control mr-2" id="status" name="status"><option value="">All messages</option>@foreach(['new','read','replied','archived'] as $state)<option value="{{ $state }}" @selected(request('status') === $state)>{{ ucfirst($state) }}</option>@endforeach</select><button class="btn btn-primary" type="submit">Filter</button></form></div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>ID</th><th>Received</th><th>From</th><th>Subject</th><th>Status</th><th class="text-right">Action</th></tr></thead><tbody>
@forelse($messages as $message)<tr><td>#{{ $message->id }}</td><td>{{ $message->created_at->format('d M Y, h:i A') }}</td><td><strong>{{ $message->name }}</strong><br><small>{{ $message->email }}</small></td><td>{{ ucfirst($message->subject) }}</td><td><span class="badge badge-{{ $message->status === 'new' ? 'success' : 'secondary' }}">{{ ucfirst($message->status) }}</span></td><td class="text-right"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.storefront-messages.show', $message) }}">View / Manage</a></td></tr>
@empty<tr><td colspan="6" class="py-5 text-center text-muted">No messages found.</td></tr>@endforelse
</tbody></table></div><div class="card-footer">{{ $messages->links('pagination::bootstrap-4') }}</div></div>
@endsection
