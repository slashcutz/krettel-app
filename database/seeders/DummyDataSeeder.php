<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Banner;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Comment;
use App\Models\DeviceSession;
use App\Models\Favorite;
use App\Models\Genre;
use App\Models\Language;
use App\Models\Notification;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\StorageAccount;
use App\Models\Subtitle;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoAnalytic;
use App\Models\VideoAudioTrack;
use App\Models\VideoCategory;
use App\Models\WatchHistory;
use App\Support\LanguageCodes;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection as EloquentCollection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DummyDataSeeder extends Seeder
{
    use WithoutModelEvents;

    protected const IMAGES = [
        'https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=1920&auto=format&fit=crop',
        'https://images.unsplash.com/photo-1626814026160-2237a95fc5a0?q=80&w=1920&auto=format&fit=crop',
        'https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?q=80&w=1920&auto=format&fit=crop',
        'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?q=80&w=1920&auto=format&fit=crop',
        'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?q=80&w=1920&auto=format&fit=crop',
        'https://images.unsplash.com/photo-1485846234645-a62644f84728?q=80&w=1920&auto=format&fit=crop',
        'https://images.unsplash.com/photo-1517604931442-7e0c8ed2963c?q=80&w=1920&auto=format&fit=crop',
        'https://images.unsplash.com/photo-1518676590629-3dcbd9c5a5c9?q=80&w=1920&auto=format&fit=crop',
    ];

    protected const SAMPLE_STREAMS = [
        'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
        'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerEscapes.mp4',
        'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerFun.mp4',
        'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerJoyrides.mp4',
        'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerMeltdowns.mp4',
        'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/Sintel.mp4',
        'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
        'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/TearsOfSteel.mp4',
        'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4',
        'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/SubaruOutbackOnStreetAndDirt.mp4',
    ];

    protected const DEMO_VIDEOS = [
        [
            'title' => 'Dune: Part Two',
            'category' => 'Movies',
            'genres' => ['Sci-Fi', 'Action'],
            'language' => 'English',
            'short_description' => 'Paul Atreides unites with Chani and the Fremen.',
            'full_description' => 'Paul Atreides unites with Chani and the Fremen while on a warpath of revenge against the conspirators who destroyed his family. Facing a choice between the love of his life and the fate of the known universe, he endeavors to prevent a terrible future only he can foresee.',
            'duration' => 9960,
            'age_rating' => 'PG-13',
            'video_type' => 'movie',
            'release_date' => '2024-02-27',
            'tags' => 'dune,atreides,fremen,sci-fi',
            'resolution' => '4K',
        ],
        [
            'title' => 'Interstellar',
            'category' => 'Movies',
            'genres' => ['Sci-Fi', 'Drama'],
            'language' => 'English',
            'short_description' => 'A team of explorers travel through a wormhole in space.',
            'full_description' => "A team of explorers travel through a wormhole in space in an attempt to ensure humanity's survival. As Earth's resources dwindle, a group of astronauts must find a new home among the stars.",
            'duration' => 10140,
            'age_rating' => 'PG-13',
            'video_type' => 'movie',
            'release_date' => '2014-11-07',
            'tags' => 'interstellar,space,wormhole,nolan',
            'resolution' => '4K',
        ],
        [
            'title' => 'The Dark Knight',
            'category' => 'Movies',
            'genres' => ['Action', 'Crime'],
            'language' => 'English',
            'short_description' => 'Batman faces the Joker in Gotham City.',
            'full_description' => 'When the menace known as the Joker wreaks havoc and chaos on the people of Gotham, Batman must accept one of the greatest psychological and physical tests of his ability to fight injustice.',
            'duration' => 9120,
            'age_rating' => 'PG-13',
            'video_type' => 'movie',
            'release_date' => '2008-07-18',
            'tags' => 'batman,joker,gotham,crime',
            'resolution' => '1080p',
        ],
        [
            'title' => 'The Last of Us',
            'category' => 'TV Shows',
            'genres' => ['Drama', 'Horror'],
            'language' => 'English',
            'short_description' => 'Joel and Ellie journey across a post-apocalyptic America.',
            'full_description' => 'Twenty years after modern civilization has been destroyed, Joel, a hardened survivor, is hired to smuggle Ellie, a 14-year-old girl, out of an oppressive quarantine zone. What starts as a small job soon becomes a brutal, heartbreaking journey.',
            'duration' => 5220,
            'age_rating' => 'TV-MA',
            'video_type' => 'tv_show',
            'release_date' => '2023-01-15',
            'tags' => 'zombie,post-apocalyptic,hbo',
            'resolution' => '1080p',
        ],
        [
            'title' => 'Breaking Bad',
            'category' => 'TV Shows',
            'genres' => ['Crime', 'Thriller'],
            'language' => 'English',
            'short_description' => 'A chemistry teacher turns to a life of crime.',
            'full_description' => 'A chemistry teacher diagnosed with inoperable lung cancer turns to manufacturing and selling methamphetamine with a former student in order to secure his family\'s future.',
            'duration' => 2820,
            'age_rating' => 'TV-MA',
            'video_type' => 'tv_show',
            'release_date' => '2008-01-20',
            'tags' => 'heisenberg,meth,albuquerque',
            'resolution' => '1080p',
        ],
        [
            'title' => 'My Hero Academia',
            'category' => 'Anime',
            'genres' => ['Animation', 'Action'],
            'language' => 'Japanese',
            'short_description' => 'Izuku Midoriya dreams of becoming a hero.',
            'full_description' => 'In a world where eighty percent of the population has some kind of super-powered quirk, Izuku Midoriya, a quirkless boy, inherits the power of the world\'s greatest hero and enrolls in a prestigious hero academy.',
            'duration' => 1440,
            'age_rating' => 'TV-14',
            'video_type' => 'tv_show',
            'release_date' => '2016-04-03',
            'tags' => 'anime,hero,quirk,izuku',
            'resolution' => '1080p',
        ],
        [
            'title' => 'One Piece',
            'category' => 'Anime',
            'genres' => ['Animation', 'Fantasy'],
            'language' => 'Japanese',
            'short_description' => 'Monkey D. Luffy sails the Grand Line for treasure.',
            'full_description' => 'Monkey D. Luffy, a boy whose body gained the properties of rubber after unintentionally eating a Devil Fruit, sets off to find the legendary treasure One Piece and become the King of the Pirates.',
            'duration' => 1440,
            'age_rating' => 'TV-PG',
            'video_type' => 'tv_show',
            'release_date' => '1999-10-20',
            'tags' => 'anime,pirate,luffy,straw hats',
            'resolution' => '1080p',
        ],
        [
            'title' => 'Planet Earth II',
            'category' => 'Documentaries',
            'genres' => ['Documentary'],
            'language' => 'English',
            'short_description' => 'A breathtaking exploration of the natural world.',
            'full_description' => 'David Attenborough celebrates the amazing variety of the natural world in this epic documentary series, filmed over four years across 64 different countries.',
            'duration' => 3000,
            'age_rating' => 'G',
            'video_type' => 'tv_show',
            'release_date' => '2016-11-06',
            'tags' => 'nature,bbc,attenborough,documentary',
            'resolution' => '4K',
        ],
        [
            'title' => 'Demon Slayer: Swordsmith Village',
            'category' => 'Anime',
            'genres' => ['Animation', 'Action'],
            'language' => 'Japanese',
            'short_description' => 'Tanjiro hunts demons in the Swordsmith Village.',
            'full_description' => 'Tanjiro and his friends travel to the Swordsmith Village where a new demon threat awaits. The Hashira unite to protect the village and track down the upper-rank demons.',
            'duration' => 1440,
            'age_rating' => 'TV-MA',
            'video_type' => 'tv_show',
            'release_date' => '2023-04-09',
            'tags' => 'anime,demon,total concentration,sun breathing',
            'resolution' => '1080p',
        ],
        [
            'title' => 'The Office',
            'category' => 'TV Shows',
            'genres' => ['Comedy'],
            'language' => 'English',
            'short_description' => 'A mockumentary look at Dunder Mifflin.',
            'full_description' => 'A mockumentary on a group of typical office workers, where the workday consists of ego clashes, inappropriate behavior, and tedium. Michael Scott leads the Dunder Mifflin Scranton branch.',
            'duration' => 1320,
            'age_rating' => 'TV-14',
            'video_type' => 'tv_show',
            'release_date' => '2005-03-24',
            'tags' => 'comedy,michael scott,dunder mifflin',
            'resolution' => '720p',
        ],
    ];

    public function run(): void
    {
        $this->seedPermissions();
        $this->seedUsers();
        $this->seedLanguages();
        $this->seedVideoCategories();
        $this->seedGenres();
        $this->seedStorageAccounts();
        $videos = $this->seedVideos();
        $this->seedAudioTracksAndSubtitles($videos);
        $this->seedPlaylists($videos);
        $this->seedWatchHistoryAndFavorites($videos);
        $this->seedNotifications();
        $this->seedComments($videos);
        $this->seedBanners($videos);
        $this->seedCollections($videos);
        $this->seedAnalyticsSessionsAndActivity($videos);
    }

    protected function upsert(string $model, array $criteria, array $attributes): void
    {
        $record = $model::firstOrNew($criteria);
        $record->forceFill($attributes);
        $record->save();
    }

    protected function seedPermissions(): void
    {
        $permissions = ['manage videos', 'manage users', 'manage settings', 'view analytics'];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web'])->syncPermissions($permissions);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'User', 'guard_name' => 'web']);
    }

    protected function seedUsers(): void
    {
        $users = [
            ['name' => 'Demo Viewer', 'email' => 'demo@example.com'],
            ['name' => 'John Doe', 'email' => 'john@example.com'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
        ];

        foreach ($users as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'email_verified_at' => now(),
                ]
            );
            $user->assignRole('User');
        }
    }

    protected function seedLanguages(): void
    {
        foreach (LanguageCodes::names() as $name) {
            Language::firstOrCreate(
                ['code' => LanguageCodes::code($name)],
                ['name' => $name, 'status' => true]
            );
        }
    }

    protected function seedVideoCategories(): void
    {
        $categories = [
            ['name' => 'Movies', 'sort_order' => 1],
            ['name' => 'TV Shows', 'sort_order' => 2],
            ['name' => 'Web Series', 'sort_order' => 3],
            ['name' => 'Anime', 'sort_order' => 4],
            ['name' => 'Documentaries', 'sort_order' => 5],
        ];

        foreach ($categories as $data) {
            $this->upsert(VideoCategory::class, ['slug' => Str::slug($data['name'])], [
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'description' => $data['name'] . ' available on the platform.',
                'is_active' => true,
                'sort_order' => $data['sort_order'],
            ]);
        }
    }

    protected function seedGenres(): void
    {
        $genres = ['Action', 'Adventure', 'Animation', 'Comedy', 'Crime', 'Documentary', 'Drama', 'Fantasy', 'Horror', 'Mystery', 'Romance', 'Sci-Fi', 'Thriller'];

        foreach ($genres as $name) {
            $this->upsert(Genre::class, ['slug' => Str::slug($name)], [
                'name' => $name,
                'slug' => Str::slug($name),
                'status' => true,
            ]);
        }
    }

    protected function seedStorageAccounts(): void
    {
        $this->upsert(StorageAccount::class, ['name' => 'Local Disk'], [
            'name' => 'Local Disk',
            'provider' => 'local',
            'is_default' => true,
        ]);
    }

    protected function seedVideos(): EloquentCollection
    {
        $storageId = StorageAccount::where('name', 'Local Disk')->value('id');
        $videos = new EloquentCollection;

        foreach (self::DEMO_VIDEOS as $index => $data) {
            $slug = Str::slug($data['title']);
            $image = self::IMAGES[$index % count(self::IMAGES)];
            $stream = self::SAMPLE_STREAMS[$index % count(self::SAMPLE_STREAMS)];
            $language = Language::where('code', LanguageCodes::code($data['language']))->first();
            $category = VideoCategory::where('slug', Str::slug($data['category']))->first();

            $this->upsert(Video::class, ['slug' => $slug], [
                'title' => $data['title'],
                'slug' => $slug,
                'short_description' => $data['short_description'],
                'full_description' => $data['full_description'],
                'category_id' => $category->id,
                'language_id' => $language?->id,
                'tags' => $data['tags'],
                'release_date' => $data['release_date'],
                'duration' => $data['duration'],
                'age_rating' => $data['age_rating'],
                'video_type' => $data['video_type'],
                'thumbnail' => $image,
                'poster' => $image,
                'banner' => self::IMAGES[($index + 2) % count(self::IMAGES)],
                'storage_account_id' => $storageId,
                'storage_folder' => 'demo/' . $slug,
                'video_url' => $stream,
                'resolution' => $data['resolution'],
                'quality' => 'auto',
                'codec' => 'h264',
                'visibility' => 'public',
                'seo_title' => $data['title'] . ' — watch free',
                'meta_description' => $data['short_description'],
                'keywords' => $data['tags'],
                'views' => rand(1000, 250000),
                'previews' => array_values(array_unique([
                    $image,
                    self::IMAGES[($index + 1) % count(self::IMAGES)],
                    self::IMAGES[($index + 3) % count(self::IMAGES)],
                ])),
            ]);

            $video = Video::where('slug', $slug)->first();
            $videos[] = $video;

            foreach ($data['genres'] as $genreName) {
                $genre = Genre::where('slug', Str::slug($genreName))->first();
                if ($genre && ! $video->genres()->where('genres.id', $genre->id)->exists()) {
                    $video->genres()->attach($genre->id);
                }
            }
        }

        return $videos;
    }

    protected function seedAudioTracksAndSubtitles(EloquentCollection $videos): void
    {
        if (count($videos) < 2) {
            return;
        }

        $tracks = [
            0 => ['English' => true, 'Hindi' => false, 'Telugu' => false],
            8 => ['Japanese' => true, 'English' => false, 'Hindi' => false],
            6 => ['Japanese' => true, 'English' => false, 'Hindi' => false, 'Telugu' => false],
        ];

        foreach ($tracks as $videoIndex => $langs) {
            $video = $videos[$videoIndex] ?? null;
            if (! $video) {
                continue;
            }

            foreach ($langs as $name => $isDefault) {
                $language = Language::where('code', LanguageCodes::code($name))->first();
                if (! $language) {
                    continue;
                }

                $this->upsert(VideoAudioTrack::class, ['video_id' => $video->id, 'language_id' => $language->id], [
                    'video_id' => $video->id,
                    'language_id' => $language->id,
                    'file_path' => 'audios/demo/' . $video->slug . '-' . $language->code . '.m4a',
                    'label' => $name,
                    'is_default' => $isDefault,
                ]);

                $this->upsert(Subtitle::class, ['video_id' => $video->id, 'language_id' => $language->id], [
                    'video_id' => $video->id,
                    'language_id' => $language->id,
                    'file_path' => 'subtitles/demo/' . $video->slug . '-' . $language->code . '.vtt',
                    'label' => $name,
                    'is_default' => $isDefault,
                ]);
            }
        }
    }

    protected function seedPlaylists(EloquentCollection $videos): void
    {
        $users = User::all();

        foreach ($users as $user) {
            $playlists = [
                ['name' => 'Watch Later', 'visibility' => 'private', 'description' => 'Things I want to watch soon.'],
                ['name' => 'Favorites Mix', 'visibility' => 'public', 'description' => 'My favorite picks.'],
            ];

            foreach ($playlists as $data) {
                $this->upsert(Playlist::class, ['user_id' => $user->id, 'name' => $data['name']], [
                    'user_id' => $user->id,
                    'name' => $data['name'],
                    'slug' => Str::slug($data['name'] . '-' . $user->id),
                    'description' => $data['description'],
                    'visibility' => $data['visibility'],
                ]);

                $playlist = Playlist::where('user_id', $user->id)->where('name', $data['name'])->first();

                if ($playlist && count($videos) > 0) {
                    $pick = collect($videos)->shuffle()->take(rand(2, min(4, count($videos))));

                    foreach ($pick as $video) {
                        $exists = PlaylistItem::where('playlist_id', $playlist->id)->where('video_id', $video->id)->exists();
                        if (! $exists) {
                            PlaylistItem::create([
                                'playlist_id' => $playlist->id,
                                'video_id' => $video->id,
                                'sort_order' => $pick->search(function ($v) use ($video) {
                                    return $v->id === $video->id;
                                }),
                            ]);
                        }
                    }
                }
            }
        }
    }

    protected function seedWatchHistoryAndFavorites(EloquentCollection $videos): void
    {
        if (count($videos) === 0) {
            return;
        }

        $users = User::all();

        foreach ($users as $user) {
            $watched = collect($videos)->shuffle()->take(3);

            foreach ($watched as $video) {
                $this->upsert(WatchHistory::class, ['user_id' => $user->id, 'video_id' => $video->id], [
                    'user_id' => $user->id,
                    'video_id' => $video->id,
                    'progress_seconds' => rand(120, max(300, $video->duration - 120)),
                    'completed' => (bool) rand(0, 1),
                    'updated_at' => now()->subMinutes(rand(1, 5000)),
                ]);
            }

            $favorites = collect($videos)->shuffle()->take(3);
            foreach ($favorites as $video) {
                $this->upsert(Favorite::class, ['user_id' => $user->id, 'video_id' => $video->id], [
                    'user_id' => $user->id,
                    'video_id' => $video->id,
                ]);
            }
        }
    }

    protected function seedNotifications(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            $notifications = [
                ['title' => 'Welcome to Krettel!', 'message' => 'Thanks for joining. Explore thousands of movies and shows.', 'type' => 'info', 'link' => '/'],
                ['title' => 'New releases available', 'message' => 'Fresh episodes have been added to your watchlist.', 'type' => 'success', 'link' => '/browse'],
                ['title' => 'Upload complete', 'message' => 'Your video has finished processing and is now live.', 'type' => 'success', 'link' => '/admin/videos'],
            ];

            foreach ($notifications as $data) {
                $this->upsert(Notification::class, ['user_id' => $user->id, 'title' => $data['title']], [
                    'user_id' => $user->id,
                    'title' => $data['title'],
                    'message' => $data['message'],
                    'type' => $data['type'],
                    'link' => $data['link'],
                    'is_read' => false,
                ]);
            }
        }
    }

    protected function seedComments(EloquentCollection $videos): void
    {
        if (count($videos) === 0) {
            return;
        }

        $users = User::all();
        $samples = [
            'Amazing quality, the picture is crystal clear!',
            'Finally a proper platform for this. Subscribed.',
            'The audio options are a lifesaver. Thanks!',
            'Been waiting for this one for a while. Worth it.',
            'Can we get more episodes soon? Loving it.',
        ];

        foreach ($videos->take(5) as $index => $video) {
            foreach ($users->take(2) as $user) {
                $this->upsert(Comment::class, ['user_id' => $user->id, 'video_id' => $video->id, 'content' => $samples[($index + $user->id) % count($samples)]], [
                    'user_id' => $user->id,
                    'video_id' => $video->id,
                    'content' => $samples[($index + $user->id) % count($samples)],
                    'is_approved' => true,
                    'created_at' => now()->subHours(rand(1, 200)),
                ]);
            }
        }
    }

    protected function seedBanners(EloquentCollection $videos): void
    {
        if (count($videos) === 0) {
            return;
        }

        foreach ($videos->take(4) as $index => $video) {
            $this->upsert(Banner::class, ['title' => $video->title], [
                'video_id' => $video->id,
                'title' => $video->title,
                'subtitle' => 'Now Streaming',
                'image_url' => $video->banner,
                'link_url' => '/video/' . $video->slug,
                'sort_order' => $index,
                'status' => true,
            ]);
        }
    }

    protected function seedCollections(EloquentCollection $videos): void
    {
        if (count($videos) === 0) {
            return;
        }

        $collections = [
            'Action & Thriller',
            'Sci-Fi Universe',
            'Documentaries',
            'Comedy Favorites',
            'Top Rated',
        ];

        $owners = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->orWhere('email', 'demo@example.com')->get();

        foreach ($owners as $owner) {
            foreach ($collections as $index => $name) {
                $this->upsert(Collection::class, ['user_id' => $owner->id, 'name' => $name], [
                    'user_id' => $owner->id,
                    'name' => $name,
                    'slug' => Str::slug($name . '-' . $owner->id),
                    'description' => 'A curated collection of ' . $name . ' videos.',
                    'image' => self::IMAGES[$index % count(self::IMAGES)],
                    'visibility' => 'public',
                ]);

                $collection = Collection::where('user_id', $owner->id)->where('name', $name)->first();
                $pick = collect($videos)->shuffle()->take(rand(3, min(6, count($videos))));

                foreach ($pick as $order => $video) {
                    $exists = CollectionItem::where('collection_id', $collection->id)->where('video_id', $video->id)->exists();
                    if (! $exists) {
                        CollectionItem::create([
                            'collection_id' => $collection->id,
                            'video_id' => $video->id,
                            'sort_order' => $order,
                        ]);
                    }
                }
            }
        }
    }

    protected function seedAnalyticsSessionsAndActivity(EloquentCollection $videos): void
    {
        if (count($videos) === 0) {
            return;
        }

        $users = User::all();
        $deviceTypes = ['desktop', 'mobile', 'tablet', 'tv'];
        $browsers = ['Chrome', 'Firefox', 'Safari', 'Edge'];

        foreach ($users as $user) {
            $deviceId = 'demo-device-' . $user->id;
            $this->upsert(DeviceSession::class, ['user_id' => $user->id, 'device_id' => $deviceId], [
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'device_type' => $deviceTypes[array_rand($deviceTypes)],
                'browser' => $browsers[array_rand($browsers)],
                'ip_address' => '203.0.113.' . rand(1, 254),
                'last_active_at' => now()->subMinutes(rand(1, 200)),
            ]);

            foreach ($videos->take(4) as $video) {
                $this->upsert(VideoAnalytic::class, ['video_id' => $video->id, 'user_id' => $user->id], [
                    'video_id' => $video->id,
                    'user_id' => $user->id,
                    'watch_time_seconds' => rand(30, max(600, $video->duration / 2)),
                    'device_type' => $deviceTypes[array_rand($deviceTypes)],
                    'ip_address' => '203.0.113.' . rand(1, 254),
                ]);
            }

            $activity = [
                'Logged in',
                'Viewed ' . $videos->first()->title,
                'Created a playlist',
                'Added a video to Favorites',
            ];

            foreach ($activity as $description) {
                $this->upsert(ActivityLog::class, ['user_id' => $user->id, 'description' => $description], [
                    'user_id' => $user->id,
                    'description' => $description,
                    'ip_address' => '203.0.113.' . rand(1, 254),
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
                ]);
            }
        }
    }
}
