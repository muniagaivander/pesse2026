CREATE OR REPLACE SQL SECURITY INVOKER VIEW pes_sample_subsls AS SELECT * FROM master_subsls WHERE status_sampel='Ya';
CREATE OR REPLACE SQL SECURITY INVOKER VIEW pes_sample_sls AS SELECT * FROM master_sls WHERE id IN (SELECT sls_id FROM pes_sample_subsls);
CREATE OR REPLACE SQL SECURITY INVOKER VIEW pes_sample_desa AS SELECT * FROM master_desa WHERE id IN (SELECT desa_id FROM pes_sample_sls);
CREATE OR REPLACE SQL SECURITY INVOKER VIEW pes_sample_kec AS SELECT * FROM master_kec WHERE id IN (SELECT kec_id FROM pes_sample_desa);
CREATE OR REPLACE SQL SECURITY INVOKER VIEW pes_sample_kab AS SELECT * FROM master_kab WHERE id IN (SELECT kab_id FROM pes_sample_kec);
CREATE OR REPLACE SQL SECURITY INVOKER VIEW pes_sample_daily AS SELECT * FROM daily_status WHERE tanggal BETWEEN '2026-11-01' AND '2026-11-30' AND subsls_id IN (SELECT id FROM pes_sample_subsls);
CREATE OR REPLACE SQL SECURITY INVOKER VIEW pes_sample_status AS SELECT * FROM subsls_status WHERE subsls_id IN (SELECT id FROM pes_sample_subsls);
CREATE OR REPLACE SQL SECURITY INVOKER VIEW pes_sample_completion AS SELECT * FROM subsls_completion_status WHERE subsls_id IN (SELECT id FROM pes_sample_subsls);
