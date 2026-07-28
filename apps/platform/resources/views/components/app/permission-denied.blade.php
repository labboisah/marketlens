@props(['required' => null, 'title' => 'Access restricted', 'message' => null])

<x-app.empty-state
  :title="$title"
  :message="$message ?? ($required ? 'This area requires: ' . $required : 'Your account does not have permission to view this area.')"
>
  @isset($action)
    <x-slot:action>{{ $action }}</x-slot:action>
  @endisset
</x-app.empty-state>
