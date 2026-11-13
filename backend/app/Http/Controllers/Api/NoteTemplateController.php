<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NoteTemplate;
use App\Services\WorkshopAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NoteTemplateController extends Controller
{
    public function __construct(private WorkshopAccess $access) {}

    public function index(Request $request)
    {
        $profile = $this->access->requirePlan($request->user());

        return response()->json(['data' => NoteTemplate::where('technician_id', $profile->id)->orderBy('title')->get(['id', 'title', 'body', 'updated_at'])]);
    }

    public function store(Request $request)
    {
        $profile = $this->access->requirePlan($request->user());
        abort_if(NoteTemplate::where('technician_id', $profile->id)->count() >= 100, 422, 'Keep up to 100 reusable note templates.');
        $data = $request->validate($this->rules($profile->id));
        $template = new NoteTemplate($data);
        $template->technician_id = $profile->id;
        $template->save();

        return response()->json(['data' => $template], 201);
    }

    public function update(Request $request, int $template)
    {
        $profile = $this->access->requirePlan($request->user());
        $record = NoteTemplate::where('technician_id', $profile->id)->findOrFail($template);
        $record->update($request->validate($this->rules($profile->id, $record->id)));

        return response()->json(['data' => $record->fresh()]);
    }

    public function destroy(Request $request, int $template)
    {
        $profile = $this->access->requirePlan($request->user());
        NoteTemplate::where('technician_id', $profile->id)->findOrFail($template)->delete();

        return response()->noContent();
    }

    private function rules(int $profileId, ?int $ignore = null): array
    {
        return ['title' => ['required', 'string', 'max:100', Rule::unique('note_templates', 'title')->where('technician_id', $profileId)->ignore($ignore)], 'body' => ['required', 'string', 'max:2000']];
    }
}
