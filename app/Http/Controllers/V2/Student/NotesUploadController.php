<?php

namespace App\Http\Controllers\V2\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Images pasted/uploaded into a student's notes (BlockNote uploadFile hook).
 *
 * Files live on the PRIVATE local disk under notes-images/{studentHashid}/,
 * named by server-generated uuid + mime-derived extension (client names are
 * never trusted; svg is excluded - it can carry scripts). Serving is owner-only:
 * the URL embeds the student hashid and show() matches it against the session.
 */
class NotesUploadController extends Controller
{
    private const ALLOWED = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    private function student()
    {
        return auth('v2_student')->user();
    }

    public function store(Request $request)
    {
        // 2 MB cap matches the PHP upload limit so oversize fails with a clear
        // validation message instead of a dropped request body.
        $request->validate([
            'file' => ['required', 'file', 'image', 'mimes:'.implode(',', self::ALLOWED), 'max:2048'],
        ]);

        $student = $this->student();
        $ext = strtolower($request->file('file')->extension() ?: 'png'); // mime-derived, not client name
        $name = Str::uuid().'.'.$ext;

        $request->file('file')->storeAs('notes-images/'.$student->getRouteKey(), $name);

        return response()->json([
            'url' => route('v2.student.notes.images.show', ['student' => $student->getRouteKey(), 'file' => $name]),
        ]);
    }

    /** Owner-only image fetch; paths are fully whitelisted (uuid.ext under the owner's dir). */
    public function show(string $student, string $file)
    {
        abort_unless($this->student()->getRouteKey() === $student, 403);
        abort_unless(preg_match('/^[0-9a-f-]{36}\.('.implode('|', self::ALLOWED).')$/', $file), 404);

        $disk = Storage::disk('local');
        $path = 'notes-images/'.$student.'/'.$file;
        abort_unless($disk->exists($path), 404);

        // setPrivate() explicitly - passing a Cache-Control header here gets
        // normalised to "public" by BinaryFileResponse.
        $response = response()->file($disk->path($path));
        $response->setPrivate();
        $response->setMaxAge(604800);

        return $response;
    }
}
