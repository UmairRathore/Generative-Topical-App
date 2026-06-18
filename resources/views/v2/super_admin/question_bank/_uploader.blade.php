{{-- Reusable image uploader: existing thumbnails (with remove) + a dropzone with
     live previews of newly selected files.
     Props: $existing (Collection<QuestionImage>), $name (input name base),
            $multiple (bool). Removal posts ids under remove_question_images[]. --}}
@php $multiple = $multiple ?? true; @endphp
<div>
    @if ($existing->isNotEmpty())
        <div class="qb-thumbs" style="margin-bottom: 10px;">
            @foreach ($existing as $img)
                <div class="qb-thumb" x-data="{ rm: false }">
                    <img src="{{ simg($img->image_path) }}" alt="diagram" :style="rm ? 'opacity:.3; filter:grayscale(1);' : ''">
                    <label class="qb-rm">
                        <input type="checkbox" name="remove_question_images[]" value="{{ $img->id }}" x-model="rm">
                        <span x-text="rm ? 'Will remove' : 'Remove'"></span>
                    </label>
                </div>
            @endforeach
        </div>
    @endif

    <div x-data="{ files: [] }">
        <label class="qb-up">
            <input type="file" name="{{ $name }}[]" {{ $multiple ? 'multiple' : '' }} hidden
                   accept="image/png,image/jpeg,image/webp,image/gif"
                   @change="files = [...$event.target.files].map(f => ({ url: URL.createObjectURL(f), name: f.name }))">
            <template x-if="!files.length">
                <span><x-icon name="plus" size="13"/> Click to add image(s)</span>
            </template>
            <div class="qb-thumbs" x-show="files.length" style="margin-top: 2px;">
                <template x-for="f in files" :key="f.url"><img :src="f.url" :alt="f.name"></template>
            </div>
        </label>
    </div>
</div>
