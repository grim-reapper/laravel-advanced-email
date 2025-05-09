@extends('layouts.app') {{-- Assuming a base layout exists --}}

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-semibold mb-6">Email Report Dashboard</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white p-4 rounded shadow">
            <h2 class="text-gray-500 text-sm font-medium">Total Sent</h2>
            <p class="text-3xl font-semibold">{{ $totalSent }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <h2 class="text-gray-500 text-sm font-medium">Total Failed</h2>
            <p class="text-3xl font-semibold">{{ $totalFailed }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <h2 class="text-gray-500 text-sm font-medium">Total Opened</h2>
            <p class="text-3xl font-semibold">{{ $totalOpened }}</p>
            <p class="text-sm text-gray-600">Open Rate: {{ $openRate }}%</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <h2 class="text-gray-500 text-sm font-medium">Unique Clicks</h2>
            <p class="text-3xl font-semibold">{{ $totalClicks }}</p>
            <p class="text-sm text-gray-600">CTR: {{ $ctr }}%</p>
        </div>
    </div>

    <h2 class="text-xl font-semibold mb-4">Recent Emails</h2>
    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">To</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sent At</th>
                    <th scope="col" class="relative px-6 py-3">
                        <span class="sr-only">View</span>
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($recentEmails as $email)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $email->recipient_email ?? $email->recipient ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ Str::limit($email->subject, 50) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $email->status === 'sent' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ ucfirst($email->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $email->sent_at ? $email->sent_at->format('Y-m-d H:i:s') : 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="{{ route('advanced-email.report.show', $email->uuid) }}" class="text-indigo-600 hover:text-indigo-900">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No emails found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $recentEmails->links() }} {{-- Pagination links --}}
    </div>

</div>
@endsection