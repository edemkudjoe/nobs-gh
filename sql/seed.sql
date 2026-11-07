-- Sample data. Every demo account uses the password Demo@2026 (change it before any real use).


INSERT INTO regions (name) VALUES ('Greater Accra'), ('Ashanti'), ('Eastern'), ('Northern'), ('Volta'), ('Western'), ('Western North'), ('Central'), ('Oti'), ('Savannah'), ('North East'), ('Upper East'), ('Upper West'), ('Bono'), ('Bono East'), ('Ahafo');

INSERT INTO blood_groups (label) VALUES ('A+'), ('A-'), ('B+'), ('B-'), ('AB+'), ('AB-'), ('O+'), ('O-');

INSERT INTO blood_compatibility (donor_group_id, recipient_group_id)
SELECT d.id, r.id FROM blood_groups d JOIN blood_groups r ON (d.label, r.label) IN (('O-','A+'), ('O-','A-'), ('O-','B+'), ('O-','B-'), ('O-','AB+'), ('O-','AB-'), ('O-','O+'), ('O-','O-'), ('O+','O+'), ('O+','A+'), ('O+','B+'), ('O+','AB+'), ('A-','A-'), ('A-','A+'), ('A-','AB-'), ('A-','AB+'), ('A+','A+'), ('A+','AB+'), ('B-','B-'), ('B-','B+'), ('B-','AB-'), ('B-','AB+'), ('B+','B+'), ('B+','AB+'), ('AB-','AB-'), ('AB-','AB+'), ('AB+','AB+'));

-- Travel times: 10 pairs for the first 5 regions. The full 120-pair list is in travel_times_template.csv
INSERT INTO region_travel_times (from_region_id, to_region_id, hours) VALUES (1,2,4.5), (1,3,1.5), (1,4,10), (1,5,2.5), (2,3,3.5), (2,4,6), (2,5,6.5), (3,4,8.5), (3,5,3.5), (4,5,9);

INSERT INTO settings (name, value) VALUES ('shelf_life_days','35'), ('min_days_routine','3'), ('near_expiry_days','7'), ('offer_hours_urgent','4'), ('offer_hours_routine','24'), ('stale_request_days','7'), ('same_region_hours','0.5'), ('organ_viable_kidney','24'), ('organ_viable_liver','8'), ('organ_viable_pancreas','12'), ('organ_viable_heart','4'), ('organ_viable_lungs','4'), ('organ_response_fraction','0.125'), ('min_age','18'), ('max_age','65'), ('min_weight_kg','50'), ('donation_gap_months','3'), ('booking_window_days','60');

INSERT INTO stock_thresholds (facility_id, blood_group_id, min_units) SELECT NULL, id, 10 FROM blood_groups;

INSERT INTO facilities (name,type,region_id,license_no,verification_status,can_request_blood,can_report_donors,can_transplant) VALUES
('Accra Central Blood Center','blood_bank',(SELECT id FROM regions WHERE name='Greater Accra'),'BB-001','approved',FALSE,FALSE,FALSE),
('Kumasi Blood Center','blood_bank',(SELECT id FROM regions WHERE name='Ashanti'),'BB-002','approved',FALSE,FALSE,FALSE),
('Tamale Blood Center','blood_bank',(SELECT id FROM regions WHERE name='Northern'),'BB-003','approved',FALSE,FALSE,FALSE),
('Ho Blood Center','blood_bank',(SELECT id FROM regions WHERE name='Volta'),'BB-004','approved',FALSE,FALSE,FALSE),
('Korle Bu Teaching Hospital','hospital',(SELECT id FROM regions WHERE name='Greater Accra'),'HP-001','approved',TRUE,TRUE,TRUE),
('Komfo Anokye Teaching Hospital','hospital',(SELECT id FROM regions WHERE name='Ashanti'),'HP-002','approved',TRUE,TRUE,TRUE),
('Tamale Teaching Hospital','hospital',(SELECT id FROM regions WHERE name='Northern'),'HP-003','approved',TRUE,TRUE,FALSE),
('Ho Teaching Hospital','hospital',(SELECT id FROM regions WHERE name='Volta'),'HP-004','approved',TRUE,FALSE,FALSE);

INSERT INTO facilities (name,type,region_id,license_no,verification_status) VALUES ('Volta Regional Blood Bank','blood_bank',(SELECT id FROM regions WHERE name='Volta'),'BB-005','pending');

INSERT INTO users (full_name,email,password_hash,role,facility_id,is_facility_admin) VALUES
('National Administrator','admin@nobb.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','admin',NULL,FALSE),
('Accra Central Blood Center admin','admin@accra-center.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','center',(SELECT id FROM facilities WHERE name='Accra Central Blood Center'),TRUE),
('Accra Central Blood Center staff','staff@accra-center.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','center',(SELECT id FROM facilities WHERE name='Accra Central Blood Center'),FALSE),
('Kumasi Blood Center admin','admin@kumasi-center.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','center',(SELECT id FROM facilities WHERE name='Kumasi Blood Center'),TRUE),
('Kumasi Blood Center staff','staff@kumasi-center.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','center',(SELECT id FROM facilities WHERE name='Kumasi Blood Center'),FALSE),
('Tamale Blood Center admin','admin@tamale-center.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','center',(SELECT id FROM facilities WHERE name='Tamale Blood Center'),TRUE),
('Tamale Blood Center staff','staff@tamale-center.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','center',(SELECT id FROM facilities WHERE name='Tamale Blood Center'),FALSE),
('Ho Blood Center admin','admin@ho-center.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','center',(SELECT id FROM facilities WHERE name='Ho Blood Center'),TRUE),
('Ho Blood Center staff','staff@ho-center.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','center',(SELECT id FROM facilities WHERE name='Ho Blood Center'),FALSE),
('Korle Bu Teaching Hospital admin','admin@korlebu.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','hospital',(SELECT id FROM facilities WHERE name='Korle Bu Teaching Hospital'),TRUE),
('Korle Bu Teaching Hospital staff','staff@korlebu.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','hospital',(SELECT id FROM facilities WHERE name='Korle Bu Teaching Hospital'),FALSE),
('Komfo Anokye Teaching Hospital admin','admin@komfo.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','hospital',(SELECT id FROM facilities WHERE name='Komfo Anokye Teaching Hospital'),TRUE),
('Komfo Anokye Teaching Hospital staff','staff@komfo.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','hospital',(SELECT id FROM facilities WHERE name='Komfo Anokye Teaching Hospital'),FALSE),
('Tamale Teaching Hospital admin','admin@tamale-hosp.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','hospital',(SELECT id FROM facilities WHERE name='Tamale Teaching Hospital'),TRUE),
('Tamale Teaching Hospital staff','staff@tamale-hosp.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','hospital',(SELECT id FROM facilities WHERE name='Tamale Teaching Hospital'),FALSE),
('Ho Teaching Hospital admin','admin@ho-hosp.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','hospital',(SELECT id FROM facilities WHERE name='Ho Teaching Hospital'),TRUE),
('Ho Teaching Hospital staff','staff@ho-hosp.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','hospital',(SELECT id FROM facilities WHERE name='Ho Teaching Hospital'),FALSE),
('Kofi Mensah','kofi@donor.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','donor',NULL,FALSE),
('Abena Owusu','abena@donor.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','donor',NULL,FALSE),
('Yaw Boateng','yaw@donor.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','donor',NULL,FALSE),
('Esi Quaye','esi@donor.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','donor',NULL,FALSE),
('Nana Agyei','nana@donor.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','donor',NULL,FALSE),
('Selorm Dzah','selorm@donor.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','donor',NULL,FALSE),
('Adwoa Frimpong','adwoa@donor.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','donor',NULL,FALSE),
('Issah Mahama','issah@donor.example','$2y$10$6Jzdy0uURmhmrT3bFaYeguhg3Zowoc/x7fs3EredCuUwFItn6PLVe','donor',NULL,FALSE);

INSERT INTO donors (user_id,blood_group_id,date_of_birth,weight_kg,region_id,eligibility_status,eligibility_checked_at) VALUES
((SELECT id FROM users WHERE email='kofi@donor.example'),(SELECT id FROM blood_groups WHERE label='O-'),'1995-03-12',72,(SELECT id FROM regions WHERE name='Ashanti'),'passed',now()),
((SELECT id FROM users WHERE email='abena@donor.example'),(SELECT id FROM blood_groups WHERE label='A+'),'1999-07-30',58,(SELECT id FROM regions WHERE name='Greater Accra'),'passed',now()),
((SELECT id FROM users WHERE email='yaw@donor.example'),(SELECT id FROM blood_groups WHERE label='B+'),'1988-11-02',80,(SELECT id FROM regions WHERE name='Northern'),'passed',now()),
((SELECT id FROM users WHERE email='esi@donor.example'),(SELECT id FROM blood_groups WHERE label='O+'),'2001-01-19',55,(SELECT id FROM regions WHERE name='Greater Accra'),'passed',now()),
((SELECT id FROM users WHERE email='nana@donor.example'),(SELECT id FROM blood_groups WHERE label='AB-'),'1992-09-25',68,(SELECT id FROM regions WHERE name='Ashanti'),'passed',now()),
((SELECT id FROM users WHERE email='selorm@donor.example'),(SELECT id FROM blood_groups WHERE label='A-'),'1997-05-08',61,(SELECT id FROM regions WHERE name='Volta'),'passed',now()),
((SELECT id FROM users WHERE email='adwoa@donor.example'),(SELECT id FROM blood_groups WHERE label='O-'),'1990-12-14',64,(SELECT id FROM regions WHERE name='Ashanti'),'passed',now()),
((SELECT id FROM users WHERE email='issah@donor.example'),(SELECT id FROM blood_groups WHERE label='B-'),'1985-04-21',77,(SELECT id FROM regions WHERE name='Northern'),'passed',now());

INSERT INTO center_slots (facility_id, slot_date, capacity)
SELECT f.id, CURRENT_DATE + d, 20 FROM facilities f CROSS JOIN generate_series(1,14) d WHERE f.type='blood_bank' AND f.verification_status='approved';

INSERT INTO appointments (donor_id, facility_id, slot_id, status)
SELECT u.id, f.id, s.id, 'scheduled' FROM users u, facilities f, center_slots s WHERE u.email='kofi@donor.example' AND f.name='Kumasi Blood Center' AND s.facility_id=f.id AND s.slot_date=CURRENT_DATE+5;

INSERT INTO blood_units (facility_id,blood_group_id,collected_date,expiry_date,status) VALUES
((SELECT id FROM facilities WHERE name='Kumasi Blood Center'),(SELECT id FROM blood_groups WHERE label='O-'),CURRENT_DATE-(35-9),CURRENT_DATE+9,'available'),
((SELECT id FROM facilities WHERE name='Kumasi Blood Center'),(SELECT id FROM blood_groups WHERE label='O-'),CURRENT_DATE-(35-9),CURRENT_DATE+9,'available'),
((SELECT id FROM facilities WHERE name='Kumasi Blood Center'),(SELECT id FROM blood_groups WHERE label='O+'),CURRENT_DATE-(35-20),CURRENT_DATE+20,'available'),
((SELECT id FROM facilities WHERE name='Kumasi Blood Center'),(SELECT id FROM blood_groups WHERE label='O+'),CURRENT_DATE-(35-20),CURRENT_DATE+20,'available'),
((SELECT id FROM facilities WHERE name='Kumasi Blood Center'),(SELECT id FROM blood_groups WHERE label='O+'),CURRENT_DATE-(35-20),CURRENT_DATE+20,'available'),
((SELECT id FROM facilities WHERE name='Kumasi Blood Center'),(SELECT id FROM blood_groups WHERE label='O+'),CURRENT_DATE-(35-20),CURRENT_DATE+20,'available'),
((SELECT id FROM facilities WHERE name='Kumasi Blood Center'),(SELECT id FROM blood_groups WHERE label='A+'),CURRENT_DATE-(35-15),CURRENT_DATE+15,'available'),
((SELECT id FROM facilities WHERE name='Kumasi Blood Center'),(SELECT id FROM blood_groups WHERE label='A+'),CURRENT_DATE-(35-15),CURRENT_DATE+15,'available'),
((SELECT id FROM facilities WHERE name='Kumasi Blood Center'),(SELECT id FROM blood_groups WHERE label='A+'),CURRENT_DATE-(35-15),CURRENT_DATE+15,'available'),
((SELECT id FROM facilities WHERE name='Accra Central Blood Center'),(SELECT id FROM blood_groups WHERE label='O-'),CURRENT_DATE-(35-25),CURRENT_DATE+25,'available'),
((SELECT id FROM facilities WHERE name='Accra Central Blood Center'),(SELECT id FROM blood_groups WHERE label='O-'),CURRENT_DATE-(35-25),CURRENT_DATE+25,'available'),
((SELECT id FROM facilities WHERE name='Accra Central Blood Center'),(SELECT id FROM blood_groups WHERE label='O-'),CURRENT_DATE-(35-25),CURRENT_DATE+25,'available'),
((SELECT id FROM facilities WHERE name='Accra Central Blood Center'),(SELECT id FROM blood_groups WHERE label='B+'),CURRENT_DATE-(35-18),CURRENT_DATE+18,'available'),
((SELECT id FROM facilities WHERE name='Accra Central Blood Center'),(SELECT id FROM blood_groups WHERE label='B+'),CURRENT_DATE-(35-18),CURRENT_DATE+18,'available'),
((SELECT id FROM facilities WHERE name='Accra Central Blood Center'),(SELECT id FROM blood_groups WHERE label='A-'),CURRENT_DATE-(35-2),CURRENT_DATE+2,'testing'),
((SELECT id FROM facilities WHERE name='Tamale Blood Center'),(SELECT id FROM blood_groups WHERE label='O-'),CURRENT_DATE-(35-30),CURRENT_DATE+30,'available'),
((SELECT id FROM facilities WHERE name='Tamale Blood Center'),(SELECT id FROM blood_groups WHERE label='O-'),CURRENT_DATE-(35-30),CURRENT_DATE+30,'available'),
((SELECT id FROM facilities WHERE name='Tamale Blood Center'),(SELECT id FROM blood_groups WHERE label='O-'),CURRENT_DATE-(35-30),CURRENT_DATE+30,'available'),
((SELECT id FROM facilities WHERE name='Tamale Blood Center'),(SELECT id FROM blood_groups WHERE label='AB-'),CURRENT_DATE-(35-11),CURRENT_DATE+11,'available'),
((SELECT id FROM facilities WHERE name='Ho Blood Center'),(SELECT id FROM blood_groups WHERE label='A+'),CURRENT_DATE-(35-12),CURRENT_DATE+12,'available'),
((SELECT id FROM facilities WHERE name='Ho Blood Center'),(SELECT id FROM blood_groups WHERE label='A+'),CURRENT_DATE-(35-12),CURRENT_DATE+12,'available'),
((SELECT id FROM facilities WHERE name='Ho Blood Center'),(SELECT id FROM blood_groups WHERE label='O+'),CURRENT_DATE-(35-6),CURRENT_DATE+6,'available'),
((SELECT id FROM facilities WHERE name='Ho Blood Center'),(SELECT id FROM blood_groups WHERE label='O+'),CURRENT_DATE-(35-6),CURRENT_DATE+6,'available'),
((SELECT id FROM facilities WHERE name='Kumasi Blood Center'),(SELECT id FROM blood_groups WHERE label='B-'),CURRENT_DATE-(35-1),CURRENT_DATE+1,'testing'),
((SELECT id FROM facilities WHERE name='Ho Blood Center'),(SELECT id FROM blood_groups WHERE label='AB+'),CURRENT_DATE-(35-4),CURRENT_DATE+4,'available');

UPDATE blood_units SET tested_at = now() - interval '1 day' WHERE status='available';

INSERT INTO waiting_list (patient_ref,transplant_center_id,organ_type,blood_group_id,urgency,registered_at,verification_status) VALUES
('P-0388',(SELECT id FROM facilities WHERE name='Korle Bu Teaching Hospital'),'kidney',(SELECT id FROM blood_groups WHERE label='A+'),'urgent',now()-interval '12 days','verified'),
('P-0391',(SELECT id FROM facilities WHERE name='Korle Bu Teaching Hospital'),'liver',(SELECT id FROM blood_groups WHERE label='B+'),'routine',now()-interval '9 days','pending'),
('P-0394',(SELECT id FROM facilities WHERE name='Komfo Anokye Teaching Hospital'),'heart',(SELECT id FROM blood_groups WHERE label='O+'),'critical',now()-interval '4 days','pending'),
('P-0231',(SELECT id FROM facilities WHERE name='Komfo Anokye Teaching Hospital'),'kidney',(SELECT id FROM blood_groups WHERE label='O+'),'urgent',now()-interval '60 days','verified'),
('P-0302',(SELECT id FROM facilities WHERE name='Korle Bu Teaching Hospital'),'kidney',(SELECT id FROM blood_groups WHERE label='O+'),'routine',now()-interval '200 days','verified'),
('P-0150',(SELECT id FROM facilities WHERE name='Komfo Anokye Teaching Hospital'),'kidney',(SELECT id FROM blood_groups WHERE label='O-'),'routine',now()-interval '330 days','verified');

UPDATE waiting_list SET verified_by=(SELECT id FROM users WHERE email='admin@nobb.example') WHERE verification_status='verified';

INSERT INTO requests (hospital_id,blood_group_id,quantity,urgency,accept_compatible,patient_ref,status,submitted_by,created_at)
SELECT f.id,g.id,4,'critical',FALSE,'P-0412','submitted',u.id,now()-interval '1 hour' FROM facilities f,blood_groups g,users u WHERE f.name='Komfo Anokye Teaching Hospital' AND g.label='O-' AND u.email='staff@komfo.example';

INSERT INTO audit_log (user_id,action,entity,detail) SELECT id,'seed','system','Sample data loaded' FROM users WHERE email='admin@nobb.example';