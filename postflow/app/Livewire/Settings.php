<?php

namespace App\Livewire;

use App\Enums\Role;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/** My profile + notifications for everyone; team management for the admin. */
class Settings extends Component
{
    // My profile
    public string $name = '';

    public string $locale = 'fr';

    public bool $notify_email = true;

    public bool $notify_push = true;

    public int $reminder_minutes = 30;

    public string $new_password = '';

    // Team member being added or edited (admin)
    public ?int $editingUserId = null;

    public array $member = [];

    public function mount(): void
    {
        $user = auth()->user();
        $this->fill($user->only(['name', 'locale', 'notify_email', 'notify_push', 'reminder_minutes']));
    }

    public function saveProfile(): void
    {
        $data = $this->validate([
            'name' => 'required|string|max:100',
            'locale' => 'required|in:fr,en',
            'notify_email' => 'boolean',
            'notify_push' => 'boolean',
            'reminder_minutes' => 'required|integer|min:5|max:720',
            'new_password' => 'nullable|string|min:8',
        ]);

        $user = auth()->user();
        $user->fill(collect($data)->except('new_password')->all());
        if ($this->new_password !== '') {
            $user->password = Hash::make($this->new_password);
        }
        $user->save();

        $this->new_password = '';
        session()->put('locale', $user->locale);

        $this->redirectRoute('settings');
        session()->flash('toast', __('Saved'));
    }

    #[Computed]
    public function team()
    {
        return User::with('brands')->orderBy('role')->orderBy('name')->get();
    }

    #[Computed]
    public function brands()
    {
        return Brand::orderBy('position')->orderBy('name')->get();
    }

    public function newMember(): void
    {
        Gate::authorize('admin');
        $this->editingUserId = 0;
        $this->member = ['name' => '', 'email' => '', 'role' => Role::CommunityManager->value, 'password' => '', 'brands' => $this->brands->pluck('id')->map(fn ($id) => (string) $id)->all()];
    }

    public function editMember(int $id): void
    {
        Gate::authorize('admin');
        $user = User::with('brands')->findOrFail($id);
        $this->editingUserId = $user->id;
        $this->member = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'password' => '',
            'brands' => $user->brands->pluck('id')->map(fn ($id) => (string) $id)->all(),
        ];
    }

    public function saveMember(): void
    {
        Gate::authorize('admin');

        $data = $this->validate([
            'member.name' => 'required|string|max:100',
            'member.email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->editingUserId ?: null)],
            'member.role' => ['required', Rule::enum(Role::class)],
            'member.password' => [$this->editingUserId ? 'nullable' : 'required', 'string', 'min:8'],
            'member.brands' => 'array',
            'member.brands.*' => 'exists:brands,id',
        ])['member'];

        $user = $this->editingUserId ? User::findOrFail($this->editingUserId) : new User;

        // Never remove the last admin.
        if ($user->exists && $user->isAdmin() && $data['role'] !== Role::Admin->value && User::where('role', Role::Admin)->count() === 1) {
            $this->addError('member.role', __('There must be at least one admin.'));

            return;
        }

        $user->fill(['name' => $data['name'], 'email' => $data['email'], 'role' => $data['role']]);
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();
        $user->brands()->sync($data['brands'] ?? []);

        $this->editingUserId = null;
        unset($this->team);
        $this->dispatch('toast', message: __('Saved'));
    }

    public function deleteMember(int $id): void
    {
        Gate::authorize('admin');
        abort_if($id === auth()->id(), 403);

        User::findOrFail($id)->delete();
        unset($this->team);
        $this->dispatch('toast', message: __('Deleted'));
    }

    public function render()
    {
        return view('livewire.settings')->title(__('Settings'));
    }
}
