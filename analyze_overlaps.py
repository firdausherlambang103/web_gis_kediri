import psycopg2
import time
from tqdm import tqdm
import os

# --- KONFIGURASI DATABASE ---
# Pastikan konfigurasi ini sesuai dengan .env Anda yang sudah berhasil login
DB_CONFIG = {
    'dbname': 'db_webgis_kediri',          # Ganti dengan nama database Anda
    'user': 'user_webgis',          # Ganti dengan username database
    'password': 'admin321',          # Ganti dengan password database
    'host': '127.0.0.1',
    'port': '5432'
}

BATCH_SIZE = 500  # Jumlah data per proses

def get_db_connection():
    try:
        conn = psycopg2.connect(**DB_CONFIG)
        conn.autocommit = True
        return conn
    except psycopg2.OperationalError as e:
        print(f"❌ Gagal koneksi database: {e}")
        exit(1)

def run_analysis():
    print("🚀 MEMULAI ANALISIS OVERLAP (PYTHON ENGINE - FINAL FIX)")
    
    conn = get_db_connection()
    cur = conn.cursor()

    try:
        # 1. PERSIAPAN
        print("🔧 Menyiapkan Database...")
        # Kosongkan tabel hasil
        cur.execute("TRUNCATE TABLE overlap_results")
        
        # Optimasi planner
        cur.execute("VACUUM ANALYZE spatial_features")
        
        # 2. AMBIL TOTAL DATA
        cur.execute("SELECT MIN(id), MAX(id), COUNT(*) FROM spatial_features")
        result = cur.fetchone()
        
        if result is None or result[2] == 0:
            print("❌ Tabel spatial_features kosong!")
            return

        min_id, max_id, total_rows = result
        print(f"📊 Total Data: {total_rows:,} baris (ID: {min_id} s/d {max_id})")
        print(f"📦 Batch Size: {BATCH_SIZE}")

        # 3. LOOPING BATCH
        current_id = min_id - 1
        
        # Progress Bar
        pbar = tqdm(total=total_rows, unit="aset", desc="Processing")

        while current_id < max_id:
            # Ambil Batch ID
            cur.execute(f"""
                SELECT id FROM spatial_features 
                WHERE id > {current_id} 
                ORDER BY id ASC 
                LIMIT {BATCH_SIZE}
            """)
            
            batch_ids = [row[0] for row in cur.fetchall()]
            
            if not batch_ids:
                break

            last_batch_id = batch_ids[-1]
            ids_string = ",".join(map(str, batch_ids))

            # 4. QUERY SPASIAL (FIXED: HAVING -> AND)
            # - ST_IsValid menggunakan cast ::geometry
            # - ST_Area > 1 dipindah ke AND karena ini filter baris, bukan agregat
            query = f"""
                INSERT INTO overlap_results (id_1, id_2, aset_1, aset_2, desa, kecamatan, luas_overlap, created_at, updated_at)
                SELECT 
                    a.id, b.id, a.name, b.name,
                    COALESCE(a.properties->'raw_data'->>'KELURAHAN', '-'),
                    COALESCE(a.properties->'raw_data'->>'KECAMATAN', '-'),
                    ST_Area(ST_Intersection(a.geom, b.geom)::geography),
                    NOW(), NOW()
                FROM spatial_features a
                JOIN spatial_features b ON 
                    a.id < b.id 
                    AND a.geom && b.geom 
                    AND ST_Intersects(a.geom, b.geom)
                WHERE 
                    a.id IN ({ids_string}) 
                    AND ST_IsValid(a.geom::geometry) 
                    AND ST_IsValid(b.geom::geometry)
                    AND ST_Area(ST_Intersection(a.geom, b.geom)::geography) > 1
            """
            
            cur.execute(query)
            
            # Update Progress
            pbar.update(len(batch_ids))
            current_id = last_batch_id

        pbar.close()
        print("\n✅ SELESAI! Analisis tumpang tindih berhasil.")

    except Exception as e:
        print(f"\n❌ ERROR: {e}")
    
    finally:
        if cur: cur.close()
        if conn: conn.close()

if __name__ == "__main__":
    run_analysis()