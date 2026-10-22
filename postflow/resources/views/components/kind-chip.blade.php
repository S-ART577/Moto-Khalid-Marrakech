@props(['post'])
<span {{ $attributes->class(['chip eyebrow', 'kind-'.$post->kind()]) }}>
    <x-icon :name="$post->is_offer ? 'tag' : ($post->format->value === 'video' ? 'video' : 'image')" class="size-3.5" />
    {{ $post->kindLabel() }}
</span>
