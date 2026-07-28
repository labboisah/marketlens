@props(['required' => null])

<x-app.empty-state
  title="Access restricted"
  :message="$required ? 'This area requires: ' . $required : 'Your account does not have permission to view this area.'"
>
  @isset($action)
    <x-slot:action>{{ $action }}</x-slot:action>
  @endisset
</x-app.empty-state>
