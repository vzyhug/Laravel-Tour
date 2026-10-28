-- Optional setup for a NEW project. Laravel business data remains in MySQL.
-- Existing buckets are left unchanged; review their access settings in Dashboard.
insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
values ('tour-images', 'tour-images', true, 5242880,
        array['image/jpeg', 'image/png', 'image/webp'])
on conflict (id) do nothing;

-- No anonymous/authenticated write policies are created.
-- Laravel uploads use its server-only secret; public URLs are read-only access.
