<?php

namespace App\Support;

/**
 * Static Indian states / union territories and their main cities & towns (district HQs, hubs, tourist towns).
 * Used for searchable state dropdowns, city suggestions and the place-suggestion fallback when Google Places is off.
 */
final class India
{
    /** GST state code => canonical name (36 states & UTs). */
    public const STATES = [
        '35' => 'Andaman and Nicobar Islands', '37' => 'Andhra Pradesh', '12' => 'Arunachal Pradesh', '18' => 'Assam',
        '10' => 'Bihar', '04' => 'Chandigarh', '22' => 'Chhattisgarh', '26' => 'Dadra and Nagar Haveli and Daman and Diu',
        '07' => 'Delhi', '30' => 'Goa', '24' => 'Gujarat', '06' => 'Haryana', '02' => 'Himachal Pradesh',
        '01' => 'Jammu and Kashmir', '20' => 'Jharkhand', '29' => 'Karnataka', '32' => 'Kerala', '38' => 'Ladakh',
        '31' => 'Lakshadweep', '23' => 'Madhya Pradesh', '27' => 'Maharashtra', '14' => 'Manipur', '17' => 'Meghalaya',
        '15' => 'Mizoram', '13' => 'Nagaland', '21' => 'Odisha', '34' => 'Puducherry', '03' => 'Punjab',
        '08' => 'Rajasthan', '11' => 'Sikkim', '33' => 'Tamil Nadu', '36' => 'Telangana', '16' => 'Tripura',
        '09' => 'Uttar Pradesh', '05' => 'Uttarakhand', '19' => 'West Bengal',
    ];

    /** Common short forms / old names (lower-case) => canonical name. */
    private const ALIASES = [
        'mp' => 'Madhya Pradesh', 'm.p.' => 'Madhya Pradesh', 'up' => 'Uttar Pradesh', 'u.p.' => 'Uttar Pradesh',
        'ap' => 'Andhra Pradesh', 'hp' => 'Himachal Pradesh', 'tn' => 'Tamil Nadu', 'wb' => 'West Bengal',
        'j&k' => 'Jammu and Kashmir', 'jammu & kashmir' => 'Jammu and Kashmir', 'jk' => 'Jammu and Kashmir',
        'orissa' => 'Odisha', 'pondicherry' => 'Puducherry', 'uttaranchal' => 'Uttarakhand', 'new delhi' => 'Delhi',
        'nct of delhi' => 'Delhi', 'mh' => 'Maharashtra', 'gj' => 'Gujarat', 'rj' => 'Rajasthan', 'ka' => 'Karnataka',
        'kl' => 'Kerala', 'cg' => 'Chhattisgarh', 'chattisgarh' => 'Chhattisgarh', 'ts' => 'Telangana', 'tg' => 'Telangana',
        'andaman & nicobar islands' => 'Andaman and Nicobar Islands', 'daman and diu' => 'Dadra and Nagar Haveli and Daman and Diu',
        'dadra and nagar haveli' => 'Dadra and Nagar Haveli and Daman and Diu',
    ];

    public const CITIES = [
        'Andaman and Nicobar Islands' => ['Port Blair', 'Havelock Island', 'Neil Island', 'Diglipur', 'Mayabunder', 'Rangat', 'Car Nicobar'],
        'Andhra Pradesh' => ['Visakhapatnam', 'Vijayawada', 'Guntur', 'Nellore', 'Kurnool', 'Tirupati', 'Rajahmundry', 'Kakinada', 'Kadapa', 'Anantapur', 'Eluru', 'Ongole', 'Vizianagaram', 'Srikakulam', 'Machilipatnam', 'Chittoor', 'Tenali', 'Proddatur', 'Hindupur', 'Bhimavaram', 'Amaravati', 'Srisailam', 'Puttaparthi', 'Nandyal', 'Adoni', 'Madanapalle', 'Araku Valley'],
        'Arunachal Pradesh' => ['Itanagar', 'Naharlagun', 'Tawang', 'Pasighat', 'Ziro', 'Bomdila', 'Tezu', 'Along', 'Roing', 'Dirang'],
        'Assam' => ['Guwahati', 'Dibrugarh', 'Silchar', 'Jorhat', 'Tezpur', 'Nagaon', 'Tinsukia', 'Bongaigaon', 'Dhubri', 'Sivasagar', 'Goalpara', 'Barpeta', 'Karimganj', 'Diphu', 'Golaghat', 'Lakhimpur', 'Kaziranga', 'Majuli', 'Haflong', 'Dispur'],
        'Bihar' => ['Patna', 'Gaya', 'Bodh Gaya', 'Bhagalpur', 'Muzaffarpur', 'Darbhanga', 'Purnia', 'Arrah', 'Begusarai', 'Katihar', 'Munger', 'Chhapra', 'Saharsa', 'Sasaram', 'Hajipur', 'Dehri', 'Siwan', 'Motihari', 'Nalanda', 'Rajgir', 'Bettiah', 'Sitamarhi', 'Buxar', 'Madhubani', 'Kishanganj', 'Aurangabad (Bihar)', 'Nawada', 'Jehanabad'],
        'Chandigarh' => ['Chandigarh'],
        'Chhattisgarh' => ['Raipur', 'Bhilai', 'Durg', 'Bilaspur', 'Korba', 'Rajnandgaon', 'Raigarh', 'Jagdalpur', 'Ambikapur', 'Dhamtari', 'Mahasamund', 'Kanker', 'Janjgir', 'Kawardha', 'Dantewada', 'Naya Raipur', 'Chirmiri', 'Bemetara'],
        'Dadra and Nagar Haveli and Daman and Diu' => ['Daman', 'Diu', 'Silvassa'],
        'Delhi' => ['New Delhi', 'Delhi', 'Dwarka', 'Rohini', 'Saket', 'Connaught Place', 'Karol Bagh', 'Laxmi Nagar', 'Janakpuri', 'Pitampura', 'Vasant Kunj', 'Mayur Vihar', 'Chandni Chowk', 'Lajpat Nagar', 'Nehru Place', 'Paharganj', 'Indira Gandhi International Airport (DEL)', 'New Delhi Railway Station', 'Hazrat Nizamuddin', 'Anand Vihar'],
        'Goa' => ['Panaji', 'Margao', 'Vasco da Gama', 'Mapusa', 'Ponda', 'Calangute', 'Baga', 'Candolim', 'Anjuna', 'Palolem', 'Colva', 'Old Goa', 'Mopa Airport (GOX)', 'Dabolim Airport (GOI)', 'Arambol', 'Madgaon Railway Station'],
        'Gujarat' => ['Ahmedabad', 'Surat', 'Vadodara', 'Rajkot', 'Bhavnagar', 'Jamnagar', 'Junagadh', 'Gandhinagar', 'Anand', 'Navsari', 'Morbi', 'Nadiad', 'Surendranagar', 'Bharuch', 'Mehsana', 'Bhuj', 'Porbandar', 'Palanpur', 'Valsad', 'Vapi', 'Godhra', 'Patan', 'Dwarka', 'Somnath', 'Veraval', 'Amreli', 'Ambaji', 'Kevadia (Statue of Unity)', 'Sasan Gir', 'Saputara', 'Dahod', 'Himmatnagar', 'Ankleshwar', 'Gandhidham', 'Botad', 'Palitana', 'Modasa'],
        'Haryana' => ['Gurugram', 'Faridabad', 'Panipat', 'Ambala', 'Yamunanagar', 'Rohtak', 'Hisar', 'Karnal', 'Sonipat', 'Panchkula', 'Bhiwani', 'Sirsa', 'Bahadurgarh', 'Jind', 'Thanesar', 'Kurukshetra', 'Kaithal', 'Rewari', 'Palwal', 'Fatehabad', 'Jhajjar', 'Narnaul', 'Manesar', 'Nuh'],
        'Himachal Pradesh' => ['Shimla', 'Manali', 'Dharamshala', 'McLeod Ganj', 'Kullu', 'Mandi', 'Solan', 'Dalhousie', 'Kasauli', 'Chamba', 'Bilaspur (HP)', 'Hamirpur (HP)', 'Una', 'Palampur', 'Kangra', 'Nahan', 'Kufri', 'Chail', 'Keylong', 'Kaza', 'Bir Billing', 'Kasol', 'Reckong Peo', 'Sundernagar', 'Baddi'],
        'Jammu and Kashmir' => ['Srinagar', 'Jammu', 'Gulmarg', 'Pahalgam', 'Sonamarg', 'Katra', 'Anantnag', 'Baramulla', 'Udhampur', 'Kathua', 'Sopore', 'Pulwama', 'Kupwara', 'Rajouri', 'Poonch', 'Doda', 'Patnitop', 'Kishtwar', 'Ganderbal', 'Budgam'],
        'Jharkhand' => ['Ranchi', 'Jamshedpur', 'Dhanbad', 'Bokaro', 'Deoghar', 'Hazaribagh', 'Giridih', 'Ramgarh', 'Phusro', 'Medininagar', 'Chaibasa', 'Dumka', 'Sahibganj', 'Godda', 'Chatra', 'Lohardaga', 'Gumla', 'Koderma', 'Pakur', 'Jamtara'],
        'Karnataka' => ['Bengaluru', 'Mysuru', 'Mangaluru', 'Hubballi', 'Dharwad', 'Belagavi', 'Kalaburagi', 'Davanagere', 'Ballari', 'Vijayapura', 'Shivamogga', 'Tumakuru', 'Raichur', 'Bidar', 'Hosapete', 'Hampi', 'Udupi', 'Hassan', 'Chikkamagaluru', 'Madikeri (Coorg)', 'Mandya', 'Chitradurga', 'Kolar', 'Gokarna', 'Karwar', 'Bagalkot', 'Gadag', 'Chamarajanagar', 'Ramanagara', 'Kempegowda International Airport (BLR)', 'Murudeshwar', 'Dandeli', 'Sakleshpur', 'Kundapura'],
        'Kerala' => ['Thiruvananthapuram', 'Kochi', 'Kozhikode', 'Thrissur', 'Kollam', 'Kannur', 'Alappuzha', 'Palakkad', 'Kottayam', 'Malappuram', 'Munnar', 'Thekkady', 'Varkala', 'Kovalam', 'Wayanad', 'Kasaragod', 'Pathanamthitta', 'Idukki', 'Guruvayur', 'Kumarakom', 'Sabarimala', 'Cochin International Airport (COK)', 'Ernakulam', 'Kalpetta', 'Vagamon', 'Bekal'],
        'Ladakh' => ['Leh', 'Kargil', 'Nubra Valley', 'Pangong Lake', 'Diskit', 'Hanle', 'Zanskar'],
        'Lakshadweep' => ['Kavaratti', 'Agatti', 'Minicoy', 'Bangaram'],
        'Madhya Pradesh' => ['Indore', 'Bhopal', 'Jabalpur', 'Gwalior', 'Ujjain', 'Sagar', 'Dewas', 'Satna', 'Ratlam', 'Rewa', 'Murwara (Katni)', 'Singrauli', 'Burhanpur', 'Khandwa', 'Bhind', 'Chhindwara', 'Guna', 'Shivpuri', 'Vidisha', 'Chhatarpur', 'Damoh', 'Mandsaur', 'Khargone', 'Neemuch', 'Pithampur', 'Hoshangabad (Narmadapuram)', 'Itarsi', 'Sehore', 'Betul', 'Seoni', 'Datia', 'Nagda', 'Dhar', 'Mhow', 'Omkareshwar', 'Maheshwar', 'Mandu', 'Pachmarhi', 'Khajuraho', 'Orchha', 'Sanchi', 'Amarkantak', 'Chitrakoot', 'Maihar', 'Shahdol', 'Balaghat', 'Mandla', 'Tikamgarh', 'Panna', 'Morena', 'Rajgarh', 'Shajapur', 'Barwani', 'Jhabua', 'Alirajpur', 'Anuppur', 'Umaria', 'Sidhi', 'Waidhan', 'Dindori', 'Harda', 'Raisen', 'Ashoknagar', 'Agar Malwa', 'Niwari', 'Narsinghpur', 'Sheopur', 'Kanha', 'Bandhavgarh', 'Devi Ahilyabai Holkar Airport (IDR)', 'Raja Bhoj Airport (BHO)'],
        'Maharashtra' => ['Mumbai', 'Pune', 'Nagpur', 'Thane', 'Nashik', 'Chhatrapati Sambhajinagar (Aurangabad)', 'Solapur', 'Navi Mumbai', 'Kalyan', 'Vasai-Virar', 'Kolhapur', 'Amravati', 'Sangli', 'Malegaon', 'Jalgaon', 'Akola', 'Latur', 'Dhule', 'Ahmednagar (Ahilyanagar)', 'Chandrapur', 'Parbhani', 'Ichalkaranji', 'Jalna', 'Bhusawal', 'Panvel', 'Satara', 'Beed', 'Yavatmal', 'Nanded', 'Gondia', 'Wardha', 'Ratnagiri', 'Sindhudurg', 'Alibag', 'Lonavala', 'Khandala', 'Mahabaleshwar', 'Panchgani', 'Shirdi', 'Shani Shingnapur', 'Pandharpur', 'Trimbakeshwar', 'Matheran', 'Igatpuri', 'Ganpatipule', 'Tarkarli', 'Lavasa', 'Bhimashankar', 'Ajanta', 'Ellora', 'Osmanabad (Dharashiv)', 'Hingoli', 'Washim', 'Buldhana', 'Bhandara', 'Gadchiroli', 'Palghar', 'Raigad', 'Baramati', 'Mumbai Airport (BOM)', 'Pune Airport (PNQ)', 'Chhatrapati Shivaji Maharaj Terminus (CSMT)'],
        'Manipur' => ['Imphal', 'Thoubal', 'Bishnupur', 'Churachandpur', 'Ukhrul', 'Senapati', 'Kakching', 'Moirang', 'Moreh'],
        'Meghalaya' => ['Shillong', 'Cherrapunji (Sohra)', 'Tura', 'Jowai', 'Nongpoh', 'Dawki', 'Mawlynnong', 'Nongstoin', 'Williamnagar', 'Baghmara'],
        'Mizoram' => ['Aizawl', 'Lunglei', 'Champhai', 'Serchhip', 'Kolasib', 'Saiha', 'Lawngtlai', 'Mamit'],
        'Nagaland' => ['Kohima', 'Dimapur', 'Mokokchung', 'Wokha', 'Tuensang', 'Mon', 'Zunheboto', 'Phek', 'Kiphire', 'Longleng'],
        'Odisha' => ['Bhubaneswar', 'Cuttack', 'Rourkela', 'Puri', 'Berhampur', 'Sambalpur', 'Balasore', 'Bhadrak', 'Baripada', 'Jharsuguda', 'Jeypore', 'Angul', 'Bargarh', 'Rayagada', 'Dhenkanal', 'Kendrapara', 'Jajpur', 'Paradip', 'Konark', 'Koraput', 'Bolangir', 'Keonjhar', 'Gopalpur', 'Chilika', 'Kalahandi (Bhawanipatna)', 'Sundargarh', 'Phulbani', 'Nayagarh'],
        'Puducherry' => ['Puducherry', 'Karaikal', 'Mahe', 'Yanam', 'Auroville'],
        'Punjab' => ['Ludhiana', 'Amritsar', 'Jalandhar', 'Patiala', 'Bathinda', 'Mohali', 'Hoshiarpur', 'Pathankot', 'Moga', 'Batala', 'Abohar', 'Malerkotla', 'Khanna', 'Phagwara', 'Muktsar', 'Barnala', 'Rajpura', 'Firozpur', 'Kapurthala', 'Faridkot', 'Sangrur', 'Fazilka', 'Gurdaspur', 'Mansa', 'Ropar (Rupnagar)', 'Anandpur Sahib', 'Zirakpur', 'Tarn Taran', 'Nawanshahr', 'Wagah Border'],
        'Rajasthan' => ['Jaipur', 'Jodhpur', 'Udaipur', 'Kota', 'Bikaner', 'Ajmer', 'Pushkar', 'Bhilwara', 'Alwar', 'Bharatpur', 'Sikar', 'Sri Ganganagar', 'Pali', 'Jaisalmer', 'Mount Abu', 'Chittorgarh', 'Barmer', 'Tonk', 'Kishangarh', 'Beawar', 'Hanumangarh', 'Dhaulpur', 'Sawai Madhopur', 'Ranthambore', 'Churu', 'Jhunjhunu', 'Nathdwara', 'Kumbhalgarh', 'Bundi', 'Banswara', 'Dungarpur', 'Jhalawar', 'Nagaur', 'Sirohi', 'Rajsamand', 'Dausa', 'Karauli', 'Mandawa', 'Khatu Shyam Ji', 'Salasar', 'Neemrana', 'Bhiwadi', 'Pratapgarh (Rajasthan)', 'Jalore', 'Baran'],
        'Sikkim' => ['Gangtok', 'Namchi', 'Pelling', 'Lachung', 'Lachen', 'Ravangla', 'Mangan', 'Gyalshing', 'Yuksom', 'Nathula', 'Tsomgo Lake', 'Zuluk'],
        'Tamil Nadu' => ['Chennai', 'Coimbatore', 'Madurai', 'Tiruchirappalli', 'Salem', 'Tirunelveli', 'Tiruppur', 'Vellore', 'Erode', 'Thoothukudi', 'Dindigul', 'Thanjavur', 'Ranipet', 'Sivakasi', 'Karur', 'Ooty (Udhagamandalam)', 'Kodaikanal', 'Hosur', 'Nagercoil', 'Kanchipuram', 'Kanyakumari', 'Rameswaram', 'Mahabalipuram', 'Kumbakonam', 'Cuddalore', 'Pollachi', 'Tiruvannamalai', 'Nagapattinam', 'Velankanni', 'Yercaud', 'Coonoor', 'Krishnagiri', 'Dharmapuri', 'Namakkal', 'Pudukkottai', 'Karaikudi', 'Chidambaram', 'Palani', 'Chennai Airport (MAA)', 'Chengalpattu', 'Villupuram', 'Ariyalur', 'Theni', 'Virudhunagar'],
        'Telangana' => ['Hyderabad', 'Secunderabad', 'Warangal', 'Nizamabad', 'Karimnagar', 'Khammam', 'Ramagundam', 'Mahbubnagar', 'Nalgonda', 'Adilabad', 'Suryapet', 'Siddipet', 'Miryalaguda', 'Jagtial', 'Mancherial', 'Sangareddy', 'Kamareddy', 'Bhadrachalam', 'Yadagirigutta', 'Medak', 'Vikarabad', 'Rajiv Gandhi International Airport (HYD)', 'Gachibowli', 'HITEC City', 'Kukatpally', 'Shamshabad'],
        'Tripura' => ['Agartala', 'Udaipur (Tripura)', 'Dharmanagar', 'Kailashahar', 'Belonia', 'Ambassa', 'Khowai', 'Teliamura'],
        'Uttar Pradesh' => ['Lucknow', 'Kanpur', 'Ghaziabad', 'Agra', 'Varanasi', 'Meerut', 'Prayagraj', 'Bareilly', 'Aligarh', 'Moradabad', 'Saharanpur', 'Gorakhpur', 'Noida', 'Greater Noida', 'Firozabad', 'Jhansi', 'Muzaffarnagar', 'Mathura', 'Vrindavan', 'Ayodhya', 'Rampur', 'Shahjahanpur', 'Farrukhabad', 'Mau', 'Hapur', 'Etawah', 'Mirzapur', 'Bulandshahr', 'Sambhal', 'Amroha', 'Hardoi', 'Fatehpur', 'Raebareli', 'Orai', 'Sitapur', 'Bahraich', 'Modinagar', 'Unnao', 'Jaunpur', 'Lakhimpur', 'Hathras', 'Banda', 'Pilibhit', 'Barabanki', 'Mughalsarai (Pt. DD Upadhyaya Nagar)', 'Gonda', 'Mainpuri', 'Lalitpur', 'Etah', 'Deoria', 'Basti', 'Azamgarh', 'Ballia', 'Ghazipur', 'Sultanpur', 'Pratapgarh', 'Chitrakoot (UP)', 'Kushinagar', 'Sarnath', 'Naimisharanya', 'Fatehpur Sikri', 'Badaun', 'Bijnor', 'Kasganj', 'Lucknow Airport (LKO)', 'Varanasi Airport (VNS)', 'Jewar (Noida International Airport)'],
        'Uttarakhand' => ['Dehradun', 'Haridwar', 'Rishikesh', 'Mussoorie', 'Nainital', 'Haldwani', 'Roorkee', 'Rudrapur', 'Kashipur', 'Almora', 'Pithoragarh', 'Ranikhet', 'Kausani', 'Jim Corbett (Ramnagar)', 'Kedarnath', 'Badrinath', 'Gangotri', 'Yamunotri', 'Joshimath (Jyotirmath)', 'Auli', 'Chopta', 'Rudraprayag', 'Uttarkashi', 'Tehri', 'Srinagar (Garhwal)', 'Guptkashi', 'Sonprayag', 'Lansdowne', 'Bhimtal', 'Kathgodam', 'Mukteshwar', 'Chamoli', 'Pauri', 'Champawat', 'Bageshwar', 'Barkot', 'Jolly Grant Airport (DED)'],
        'West Bengal' => ['Kolkata', 'Howrah', 'Durgapur', 'Asansol', 'Siliguri', 'Darjeeling', 'Kalimpong', 'Bardhaman', 'Malda', 'Baharampur', 'Habra', 'Kharagpur', 'Haldia', 'Shantiniketan (Bolpur)', 'Digha', 'Mandarmani', 'Krishnanagar', 'Jalpaiguri', 'Cooch Behar', 'Bankura', 'Purulia', 'Medinipur', 'Raiganj', 'Balurghat', 'Alipurduar', 'Mirik', 'Sundarbans', 'Bagdogra Airport (IXB)', 'Netaji Subhas Chandra Bose Airport (CCU)', 'New Jalpaiguri (NJP)', 'Barasat', 'Serampore', 'Tarakeswar', 'Mayapur', 'Bakkhali'],
    ];

    /** @return list<string> */
    public static function states(): array
    {
        $s = array_values(self::STATES);
        sort($s);

        return $s;
    }

    /** Canonical state name for free text / short forms; null when unrecognised. */
    public static function normalizeState(?string $value): ?string
    {
        $v = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $value)));
        if ($v === '') {
            return null;
        }
        foreach (self::STATES as $name) {
            if (mb_strtolower($name) === $v) {
                return $name;
            }
        }

        return self::ALIASES[$v] ?? null;
    }

    public static function stateCode(?string $state): ?string
    {
        $name = self::normalizeState($state);

        return $name ? (string) array_search($name, self::STATES, true) : null;
    }

    /**
     * Cities matching the query (prefix matches first), optionally limited to a state.
     *
     * @return list<array{city:string, state:string}>
     */
    public static function searchCities(string $query, ?string $state = null, int $limit = 8): array
    {
        $q = mb_strtolower(trim($query));
        $state = self::normalizeState($state);
        $pool = $state ? [$state => self::CITIES[$state] ?? []] : self::CITIES;
        $prefix = $contains = [];

        foreach ($pool as $st => $cities) {
            foreach ($cities as $city) {
                $c = mb_strtolower($city);
                if ($q === '' || str_starts_with($c, $q)) {
                    $prefix[] = ['city' => $city, 'state' => $st];
                } elseif (str_contains($c, $q)) {
                    $contains[] = ['city' => $city, 'state' => $st];
                }
            }
        }

        return array_slice(array_merge($prefix, $contains), 0, $limit);
    }
}
