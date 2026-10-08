<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Note::where('user_id', $request->user()->id)
                ->with('lesson:id,title,category_id')
                ->orderByDesc('updated_at')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lesson_id' => 'required|exists:lessons,id',
            'content' => 'required|string|max:10000'
        ]);

        $note = Note::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'lesson_id' => $validated['lesson_id']
            ],
            [
                'content' => strip_tags($validated['content'])
            ]
        );

        return response()->json([
            'message' => 'Jegyzet mentve!',
            'note' => $note->load('lesson:id,title,category_id')
        ]);
    }

    public function show(Request $request, Note $note)
    {
        $this->checkOwner($request, $note);

        return response()->json(
            $note->load('lesson:id,title,category_id')
        );
    }

    public function update(Request $request, Note $note)
    {
        $this->checkOwner($request, $note);

        $validated = $request->validate([
            'content' => 'required|string|max:10000'
        ]);

        $note->update([
            'content' => strip_tags($validated['content'])
        ]);

        return response()->json([
            'message' => 'Jegyzet frissítve!',
            'note' => $note->fresh()->load('lesson:id,title,category_id')
        ]);
    }

    public function destroy(Request $request, Note $note)
    {
        $this->checkOwner($request, $note);
        $note->delete();

        return response()->json([
            'message' => 'Jegyzet törölve!'
        ]);
    }

    private function checkOwner(Request $request, Note $note): void
    {
        abort_if($note->user_id !== $request->user()->id, 403, 'Nincs jogosultságod ehhez a jegyzethez.');
    }
}