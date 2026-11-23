@php $map = ['assigned' => ['info', __('Assigned')], 'started' => ['ok', __('In progress')], 'completed' => ['muted', __('Completed')], 'released' => ['danger', __('Cancelled')]]; [$tone, $label] = $map[$status] ?? ['muted', $status]; @endphp
<span class="badge-soft {{ $tone }} text-nowrap">{{ $label }}</span>
