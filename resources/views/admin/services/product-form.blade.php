<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <label><span class="label">Tên sản phẩm</span><input class="field" name="name" value="{{ old('name',$product?->name) }}" maxlength="255" required></label>
    <label><span class="label">Loại dịch vụ</span><select class="field" name="type" @disabled($product)>@foreach(\App\Enums\ServiceType::cases() as $type)<option value="{{ $type->value }}" @selected(old('type',$product?->type)===$type->value)>{{ $type->label() }}</option>@endforeach</select>@if($product)<input type="hidden" name="type" value="{{ $product->type }}">@endif</label>
    <label><span class="label">Nhà cung cấp</span><select class="field" name="supplier_id" required>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id',$product?->supplier_id)==$supplier->id)>{{ $supplier->name }}</option>@endforeach</select></label>
    <label><span class="label">Địa điểm / tuyến</span><input class="field" name="location" value="{{ old('location',$product?->location) }}" maxlength="255" required></label>
    <label><span class="label">Ảnh địa danh minh họa (local)</span><input class="field" name="image_path" value="{{ old('image_path',$product?->image_path ?? 'images/tours/demo/da-nang-2.jpg') }}"></label>
    <label><span class="label">Hạng lưu trú demo (0–5 sao)</span><input class="field" type="number" name="stars" min="0" max="5" value="{{ old('stars',$product?->attributes['stars'] ?? 0) }}"></label>
    <label class="sm:col-span-2"><span class="label">Tiện ích (ngăn bởi dấu phẩy)</span><input class="field" name="amenities" value="{{ old('amenities',implode(', ',$product?->attributes['amenities'] ?? [])) }}"></label>
    <label class="sm:col-span-2 lg:col-span-3"><span class="label">Mô tả, điều kiện vị trí và phạm vi demo</span><textarea class="field" name="description" rows="4" maxlength="10000" required>{{ old('description',$product?->description) }}</textarea></label>
    <label class="flex items-center gap-3 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$product?->is_active ?? true))> Mở bán sản phẩm mới</label>
</div>
