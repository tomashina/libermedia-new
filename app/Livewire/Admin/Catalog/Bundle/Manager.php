<?php

namespace App\Livewire\Admin\Catalog\Bundle;

use App\Models\Catalog\Bundle\Bundle;
use App\Models\Catalog\Product\Product;
use App\Models\User\CustomerGroup;
use App\Services\Front\CartService;
use App\Services\Front\GuestStorefrontCache;
use App\Support\StorefrontTheme;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Manager extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $editingId = null;

    public bool $editing = false;

    public string $search = '';

    public string $status = '';

    public string $sort = 'order';

    public string $productSearch = '';

    public string $title = '';

    public bool $isActive = false;

    public ?string $startsAt = null;

    public ?string $endsAt = null;

    public int $sortOrder = 0;

    public array $products = [];

    public array $discounts = [];

    private function permit(string $ability): void
    {
        abort_unless($this->can($ability), 403);
    }

    private function can(string $ability): bool
    {
        $user = auth()->user();

        return $user && ($user->isA('superadmin') || $user->can('catalog.actions.'.$ability));
    }

    public function mount(): void
    {
        $this->permit('view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        if (! in_array($this->status, ['', 'active', 'scheduled', 'expired', 'disabled'], true)) {
            $this->status = '';
        }
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        if (! in_array($this->sort, ['order', 'newest', 'title', 'expiry'], true)) {
            $this->sort = 'order';
        }
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'sort');
        $this->resetPage();
    }

    public function cancelEditing(): void
    {
        $this->reset('editing', 'editingId', 'title', 'isActive', 'startsAt', 'endsAt', 'sortOrder', 'products', 'discounts', 'productSearch');
        $this->resetValidation();
    }

    public function create(): void
    {
        $this->permit('create');
        $this->cancelEditing();
        $this->editing = true;
        $group = $this->defaultDiscountGroup()
            ?? ($this->isB2cEditor() ? null : CustomerGroup::where('is_active', true)->orderBy('id')->first());
        if ($group) {
            $this->discounts = [['customer_group_id' => $group->id, 'type' => 'percent', 'value' => 10]];
        }
    }

    public function edit(int $id): void
    {
        $this->permit('update');
        $bundle = Bundle::with(['products', 'discounts'])->findOrFail($id);
        $this->cancelEditing();
        $this->resetValidation();
        $this->editingId = $bundle->id;
        $this->editing = true;
        $this->title = $bundle->title;
        $this->isActive = $bundle->is_active;
        $this->startsAt = $this->formatBundleDate($bundle->starts_at);
        $this->endsAt = $this->formatBundleDate($bundle->ends_at);
        $this->sortOrder = $bundle->sort_order;
        $this->products = $bundle->products->map(fn ($p) => ['product_id' => $p->id, 'quantity' => $p->pivot->quantity])->all();
        $this->discounts = $bundle->discounts->map(fn ($d) => ['customer_group_id' => $d->customer_group_id, 'type' => $d->type, 'value' => $d->value])->all();
        if ($this->isB2cEditor() && $this->defaultDiscountIndex($this->defaultDiscountGroup()?->id) === null) {
            $group = $this->defaultDiscountGroup();
            if ($group) {
                $this->discounts[] = ['customer_group_id' => $group->id, 'type' => 'percent', 'value' => 0];
            }
        }
    }

    public function addProduct(int $id): void
    {
        $this->permit($this->editingId ? 'update' : 'create');
        Product::findOrFail($id);
        if (! collect($this->products)->contains('product_id', $id)) {
            $this->products[] = ['product_id' => $id, 'quantity' => 1];
        }
        $this->productSearch = '';
    }

    public function removeProduct(int $index): void
    {
        $this->permit($this->editingId ? 'update' : 'create');
        unset($this->products[$index]);
        $this->products = array_values($this->products);
    }

    public function clearProduct(int $index): void
    {
        $this->removeProduct($index);
    }

    public function addDiscount(): void
    {
        $this->permit($this->editingId ? 'update' : 'create');
        if ($this->isB2cEditor()) {
            return;
        }
        $this->discounts[] = ['customer_group_id' => '', 'type' => 'percent', 'value' => 0];
    }

    public function removeDiscount(int $index): void
    {
        $this->permit($this->editingId ? 'update' : 'create');
        unset($this->discounts[$index]);
        $this->discounts = array_values($this->discounts);
    }

    public function clearDiscount(int $index): void
    {
        $this->removeDiscount($index);
    }

    public function save(): void
    {
        $this->permit($this->editingId ? 'update' : 'create');
        $isB2cEditor = $this->isB2cEditor();
        $defaultGroup = $this->defaultDiscountGroup();
        $discountIndex = $this->defaultDiscountIndex($defaultGroup?->id);
        if ($isB2cEditor && $discountIndex === null) {
            $this->addError('discounts', 'Postavite aktivnu zadanu grupu kupaca i popust za komplet.');

            return;
        }
        $discountPath = $isB2cEditor ? 'discounts.'.$discountIndex : 'discounts.*';
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/u'], 'isActive' => 'boolean', 'sortOrder' => 'integer|min:0|max:999999',
            'startsAt' => 'nullable|date', 'endsAt' => ['nullable', 'date', ...($this->startsAt ? ['after_or_equal:startsAt'] : [])],
            'products' => 'required|array|min:2', 'products.*.product_id' => 'required|integer|distinct|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1|max:999',
            'discounts' => 'required|array|min:1', $discountPath.'.customer_group_id' => ['required', 'integer', 'distinct', Rule::exists('customer_groups', 'id')],
            $discountPath.'.type' => ['required', Rule::in(['percent', 'fixed'])], $discountPath.'.value' => 'required|numeric|min:0|max:999999',
        ], [
            'products.required' => 'Dodajte najmanje dva različita artikla u komplet.',
            'products.min' => 'Dodajte najmanje dva različita artikla u komplet.',
            'products.*.product_id.distinct' => 'Isti artikl možete dodati samo jednom. Za više komada povećajte količinu.',
            'discounts.required' => 'Dodajte popust za komplet.',
            'discounts.min' => 'Dodajte popust za komplet.',
            'endsAt.after_or_equal' => 'Završetak ponude mora biti nakon početka ili u isto vrijeme.',
        ], [
            'title' => 'naziv ponude', 'sortOrder' => 'redoslijed', 'startsAt' => 'početak ponude', 'endsAt' => 'završetak ponude',
            'products.*.product_id' => 'artikl', 'products.*.quantity' => 'količina artikla',
            'discounts.*.customer_group_id' => 'grupa kupaca', 'discounts.*.type' => 'vrsta popusta', 'discounts.*.value' => 'vrijednost popusta',
        ]);
        $discountRows = $isB2cEditor ? [$discountIndex => $validated['discounts'][$discountIndex]] : $validated['discounts'];
        foreach ($discountRows as $i => $discount) {
            if ($discount['type'] === 'percent' && $discount['value'] > 100) {
                $this->addError('discounts.'.$i.'.value', 'Postotak mora biti između 0 i 100.');

                return;
            }
        }
        DB::transaction(function () use ($validated, $discountRows, $isB2cEditor): void {
            $bundle = $this->editingId ? Bundle::findOrFail($this->editingId) : new Bundle;
            $payload = is_array($bundle->payload) ? $bundle->payload : [];
            $payload['admin_edited_at'] = now()->toIso8601String();
            $bundle->fill(['title' => $validated['title'], 'is_active' => $validated['isActive'],
                'starts_at' => $this->storedBundleDate($this->startsAt, $bundle->starts_at),
                'ends_at' => $this->storedBundleDate($this->endsAt, $bundle->ends_at),
                'sort_order' => $validated['sortOrder'], 'payload' => $payload])->save();
            $bundle->products()->sync(collect($validated['products'])->mapWithKeys(fn ($row) => [(int) $row['product_id'] => ['quantity' => (int) $row['quantity']]])->all());
            if (! $isB2cEditor) {
                $bundle->discounts()->whereNotIn('customer_group_id', collect($discountRows)->pluck('customer_group_id'))->delete();
            }
            foreach ($discountRows as $discount) {
                $bundle->discounts()->updateOrCreate(['customer_group_id' => (int) $discount['customer_group_id']], [
                    'type' => $discount['type'], 'value' => $discount['value'],
                ]);
            }
        });
        CartService::forgetRequestSnapshot();
        app(GuestStorefrontCache::class)->invalidate();
        $this->cancelEditing();
        session()->flash('bundle_status', 'Bundle ponuda je spremljena.');
    }

    public function delete(int $id): void
    {
        $this->permit('delete');
        Bundle::findOrFail($id)->delete();
        CartService::forgetRequestSnapshot();
        app(GuestStorefrontCache::class)->invalidate();
        if ($this->editingId === $id) {
            $this->cancelEditing();
        }
        $this->resetPage();
        session()->flash('bundle_status', 'Bundle ponuda je obrisana.');
    }

    private function isB2cEditor(): bool
    {
        return StorefrontTheme::usesLibermediaBrand() || (bool) config('commerce.b2c_only');
    }

    private function defaultDiscountGroup(): ?CustomerGroup
    {
        return CustomerGroup::where('is_active', true)->where('is_default', true)->orderBy('id')->first();
    }

    private function defaultDiscountIndex(?int $groupId): ?int
    {
        if ($groupId === null) {
            return null;
        }
        foreach ($this->discounts as $index => $discount) {
            if (is_array($discount) && (int) ($discount['customer_group_id'] ?? 0) === $groupId) {
                return $index;
            }
        }

        return null;
    }

    private function displayTimezone(): string
    {
        return StorefrontTheme::usesLibermediaBrand() ? 'Europe/Zagreb' : (string) config('app.timezone', 'UTC');
    }

    private function formatBundleDate(?Carbon $date): ?string
    {
        return $date?->copy()->timezone($this->displayTimezone())->format('Y-m-d\TH:i');
    }

    private function storedBundleDate(?string $value, ?Carbon $original): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }
        // A minute-based editor must retain the existing seconds on an unchanged date.
        if ($original && $value === $this->formatBundleDate($original)) {
            return $original;
        }

        return Carbon::parse($value, $this->displayTimezone())->timezone((string) config('app.timezone', 'UTC'));
    }

    private function filterStatus(Builder $query, string $status, CarbonInterface $now): Builder
    {
        return match ($status) {
            'active' => $query->where('is_active', true)
                ->where(fn (Builder $date) => $date->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                ->where(fn (Builder $date) => $date->whereNull('ends_at')->orWhere('ends_at', '>=', $now)),
            'scheduled' => $query->where('is_active', true)->where('starts_at', '>', $now)
                ->where(fn (Builder $date) => $date->whereNull('ends_at')->orWhere('ends_at', '>=', $now)),
            'expired' => $query->where('is_active', true)->where('ends_at', '<', $now),
            'disabled' => $query->where('is_active', false),
            default => $query,
        };
    }

    public function render()
    {
        $this->permit('view');
        $ids = collect($this->products)->pluck('product_id');
        $search = mb_substr(trim($this->search), 0, 200);
        $productSearch = mb_substr(trim($this->productSearch), 0, 200);
        $now = now()->startOfSecond();
        $bundles = $this->filterStatus(Bundle::query(), $this->status, $now)
            ->with(['products.translations', 'products.media', 'discounts.group'])->withCount('products')
            ->when($search !== '', fn (Builder $query) => $query->where('title', 'like', '%'.$search.'%'));
        match ($this->sort) {
            'newest' => $bundles->orderByDesc('created_at')->orderByDesc('id'),
            'title' => $bundles->orderBy('title')->orderBy('id'),
            'expiry' => $bundles->orderByRaw('CASE WHEN ends_at IS NULL THEN 1 ELSE 0 END')->orderBy('ends_at')->orderBy('id'),
            default => $bundles->orderBy('sort_order')->orderBy('id'),
        };
        $statusCounts = ['total' => Bundle::count()];
        foreach (['active', 'scheduled', 'expired', 'disabled'] as $status) {
            $statusCounts[$status] = $this->filterStatus(Bundle::query(), $status, $now)->count();
        }
        $defaultGroup = $this->defaultDiscountGroup();

        return view('livewire.admin.catalog.bundle.manager', [
            'bundles' => $bundles->paginate(20),
            'statusCounts' => $statusCounts,
            'statusNow' => $now,
            'selectedProducts' => Product::with(['translations', 'media'])->whereIn('id', $ids)->get()->keyBy('id'),
            'productResults' => mb_strlen($productSearch) < 2 ? collect() : Product::with(['translations', 'media'])->whereNotIn('id', $ids)
                ->where(fn (Builder $query) => $query->where('sku', 'like', '%'.$productSearch.'%')->orWhere('code', 'like', '%'.$productSearch.'%')
                    ->orWhereHas('translations', fn (Builder $translation) => $translation->where('name', 'like', '%'.$productSearch.'%')))
                ->orderBy('id')->limit(15)->get(),
            'groups' => CustomerGroup::where(fn (Builder $query) => $query->where('is_active', true)
                ->orWhereIn('id', collect($this->discounts)->pluck('customer_group_id')->filter()))->orderBy('name')->get(),
            'canCreate' => $this->can('create'), 'canUpdate' => $this->can('update'), 'canDelete' => $this->can('delete'),
            'isB2cEditor' => $this->isB2cEditor(), 'defaultDiscountGroupId' => $defaultGroup?->id,
            'defaultDiscountIndex' => $this->defaultDiscountIndex($defaultGroup?->id), 'displayTimezone' => $this->displayTimezone(),
        ]);
    }
}
