<?php
/**
 * Build lmft-clinical-form-a-items-01-25.php from embedded PROMPT 01 JSON.
 *
 * Usage: php scripts/build-lmft-clinical-form-a-seed.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );
$json = <<<'JSON'
{
  "form": "A",
  "type": "learner_facing_questions",
  "question_range": "1-25",
  "questions": [
    {
      "q_num": 1,
      "stem": "A transgender man reports for ten months a marked incongruence between his experienced gender and sex characteristics, intense distress about his chest and menstrual characteristics, and a persistent desire to be rid of those characteristics. The distress remains significant in affirming environments and interferes with work and social functioning. Which diagnosis is MOST supported?",
      "choices": {
        "A": "Gender dysphoria",
        "B": "Adjustment disorder",
        "C": "Major depressive disorder",
        "D": "Body dysmorphic disorder"
      }
    },
    {
      "q_num": 2,
      "stem": "A client lost a job two months ago and reports understandable distress about finances. The client has maintained a daily routine, continues exercising, remains connected with friends and family, and has begun exploring new employment options. Which assessment would be MOST useful?",
      "choices": {
        "A": "Assess current distress, sleep, concentration, financial worry, and whether symptoms are beginning to impair work-seeking or self-care.",
        "B": "Assess social support, frequency of contact, practical assistance, and whether relationships remain available when the client needs help.",
        "C": "Assess coping behaviors, job-search activity, exercise, daily structure, and whether routines are sustaining or depleting the client\u2019s functioning.",
        "D": "Assess how routines, support, problem-solving, and personal strengths are helping maintain functioning while identifying areas that remain vulnerable over time."
      }
    },
    {
      "q_num": 3,
      "stem": "A couple requests therapy for \u201ccommunication problems.\u201d One partner describes repeated arguments about spending, while the other says the more important problem is feeling emotionally disconnected even when the couple is not arguing. What should the therapist do NEXT?",
      "choices": {
        "A": "Ask each partner for a recent interaction, what it meant, and what change would make therapy useful.",
        "B": "Ask each partner about conflict frequency, escalation patterns, and which disagreements feel most damaging to the relationship.",
        "C": "Ask each partner about family-of-origin communication, learned conflict roles, and similarities between past and current interactions.",
        "D": "Ask each partner about current stress, mood, and coping to determine how individual factors affect communication."
      }
    },
    {
      "q_num": 4,
      "stem": "An 8-year-old witnessed serious violence at school two weeks ago. Since then, the child has nightmares, asks repeatedly whether school is safe, becomes distressed when separated from a caregiver, and has difficulty returning to normal bedtime and school routines. The caregiver is calm and supportive. Which response is BEST?",
      "choices": {
        "A": "Use age-appropriate coping support, involve the caregiver in planning, and coordinate gradual school reentry while monitoring symptoms.",
        "B": "Use age-appropriate reassurance and coping, involve the caregiver in support, and restore predictable routines while monitoring symptoms closely.",
        "C": "Use structured therapeutic play and coping, involve the caregiver in support, and coordinate school planning around trauma reminders during recovery.",
        "D": "Use clear behavioral expectations and coping, involve the caregiver in support, and restore school routines while monitoring regression."
      }
    },
    {
      "q_num": 5,
      "stem": "A 32-year-old client has had almost no direct contact with the father for three years after repeated family conflict. The client still receives frequent updates through a sibling, asks the sibling to pass messages back to the father, and becomes highly anxious whenever direct contact is considered. The therapist is using a multigenerational family systems approach. Which intervention should the therapist use?",
      "choices": {
        "A": "Map recurring cutoff and triangulation patterns, identify when anxiety pulls the sibling into communication, and coach the client to observe that process.",
        "B": "Coach the client to rehearse an I-position in session, practice regulating anxiety, and prepare for direct contact with the father.",
        "C": "Map the sibling-mediated communication pattern, coach observation of family-system reactions, clarify how cutoff is maintained across relationships, and compare what changes when messages no longer pass through the sibling.",
        "D": "Coach the client to state an I-position directly to the father, maintain self-definition during the exchange, and end the sibling\u2019s intermediary role in their relationship."
      }
    },
    {
      "q_num": 6,
      "stem": "A client with chronic pain is receiving psychotherapy from the LMFT while also seeing a psychiatrist and a pain specialist. Current releases permit care coordination. The psychiatrist has encouraged greater behavioral activation, while the pain specialist recently advised reducing activity during a flare. The client is confused by the apparently conflicting recommendations and has stopped following either plan. Which coordination step should the therapist take?",
      "choices": {
        "A": "Coordinate with the psychiatrist and pain specialist separately, compare their explanations, review how each applies to the current flare, and plan an activity approach for the client from both recommendations.",
        "B": "Coordinate with the psychiatrist and pain specialist by obtaining written activity parameters, then select the recommendation that best fits the client\u2019s current flare.",
        "C": "Send both providers a shared case summary and integrate their separate written recommendations into psychotherapy.",
        "D": "Convene both providers for a joint reconciliation of the activity recommendations and a shared set of treatment parameters."
      }
    },
    {
      "q_num": 7,
      "stem": "An older adult reports gradually worsening difficulty managing finances and keeping track of complex appointments. Family members describe a similar decline, while a recent general medical evaluation did not identify an acute explanation. Which referral is MOST appropriate?",
      "choices": {
        "A": "Refer for psychiatric evaluation to determine whether an emerging mood or psychotic disorder is contributing to the cognitive complaints.",
        "B": "Refer for specialized psychological or neurocognitive testing to clarify the pattern, severity, and functional significance of the client\u2019s cognitive difficulties.",
        "C": "Repeat brief cognitive screening over several sessions and refer only if the scores show continued decline across time.",
        "D": "Refer back to primary care for another general medical evaluation before considering specialized cognitive or psychological testing."
      }
    },
    {
      "q_num": 8,
      "stem": "A clinic is designing a ten-week closed psychotherapy group for current caregivers of relatives with moderate-to-advanced dementia. Participants must be stable enough for outpatient group treatment; acute grief or crisis needs are handled separately. The goals are to reduce isolation, strengthen coping, practice communication and boundary strategies, and build practical caregiving skills. Which group framework is MOST appropriate?",
      "choices": {
        "A": "Use an open monthly support group for varied progressive-illness caregivers; offer rotating topics and peer discussion; repeat orientation for new members.",
        "B": "Use a closed ten-week dementia-caregiver education group; provide fixed lessons and home practice; screen for caregiving status and overall clinical readiness.",
        "C": "Use a closed ten-week grief group for current and bereaved caregivers; provide emotional processing and peer support; screen for general stability.",
        "D": "Use a closed ten-week therapy group; combine peer support with practical skills; screen current dementia caregivers for clinical fit and stability."
      }
    },
    {
      "q_num": 9,
      "stem": "During couples therapy, one partner repeatedly presses for immediate discussion whenever conflict occurs. The other becomes quieter, gives shorter responses, and eventually leaves the room. The first partner then follows and increases efforts to obtain a response. What should the therapist assess NEXT?",
      "choices": {
        "A": "Determine which partner usually initiates conflict, what topics trigger arguments, and whether one person\u2019s behavior reliably predicts escalation.",
        "B": "Explore childhood relationship models, attachment expectations, prior partners, conflict responses, repair patterns, and whether similar dynamics appeared in earlier relationships.",
        "C": "Assess each partner\u2019s emotion regulation, conflict tolerance, communication skills, and whether individual vulnerabilities contribute to the recurring relationship pattern over time.",
        "D": "Track a recent disagreement step by step, including each partner\u2019s response, interpretation, escalation, and effect on the other partner."
      }
    },
    {
      "q_num": 10,
      "stem": "An 11-year-old with ADHD is medically stable and receives medication management from a pediatrician. Homework often turns into a 90-minute conflict: the parent gives repeated verbal reminders, the child becomes frustrated and leaves the table, and both end the evening angry. Which intervention should the therapist use?",
      "choices": {
        "A": "Divide assignments into child-chosen order, use a timer for sustained work, and provide one parent prompt when attention drifts.",
        "B": "Move homework to a low-distraction space, set one work interval, and have the parent give feedback at the end of the interval.",
        "C": "Break homework into short work intervals with a visible sequence, and have the parent use one neutral cue instead of repeated reminders.",
        "D": "Use a point system for completed assignments, let the child record each finished task, and provide reinforcement after the homework period."
      }
    },
    {
      "q_num": 11,
      "stem": "Acute danger has been stabilized, but a client continues to experience recurrent self-injury urges, rapid emotional escalation, impulsive behavior, and treatment-interfering absences. Which treatment plan is MOST consistent with dialectical behavior therapy?",
      "choices": {
        "A": "Sequence trauma memories during exposure, teach grounding strategies, and track symptoms to regulate processing intensity.",
        "B": "Rank priority behaviors, analyze chains leading to self-injury/absence, and teach regulation plus crisis-survival skills.",
        "C": "Identify rigid demands/global self-ratings, dispute them directly, and rehearse flexible beliefs during emotional activation.",
        "D": "Explore relational expectations/unconscious conflict, examine the therapy relationship, and interpret the function of self-injury."
      }
    },
    {
      "q_num": 12,
      "stem": "A client has a history of psychiatric hospitalization, several medication changes, and multiple periods of outpatient therapy. The client now reports symptoms similar to those experienced several years ago. Which information would be MOST useful for understanding the mental-health history?",
      "choices": {
        "A": "Organize prior diagnoses, hospital admissions, treating clinicians, medications, and levels of care to compare earlier treatment periods.",
        "B": "Reconstruct symptom onset, severity, treatment changes, response, remission or relapse, and functional change across the prior episodes.",
        "C": "Review hospitalization triggers, discharge plans, post-discharge adherence, support availability, and whether baseline functioning returned afterward.",
        "D": "Review medication trials, side effects, adherence, reasons for stopping, and which symptoms improved or persisted during each treatment period."
      }
    },
    {
      "q_num": 13,
      "stem": "A 79-year-old client who depends on an adult caregiver arrives with untreated medical needs and reports frequently going without food and prescribed medication. The client also states that the caregiver has been withdrawing money from the client's account for personal purchases. The client says, \u201cPlease don't tell anyone until I can prove exactly what happened.\u201d What should the therapist do FIRST?",
      "choices": {
        "A": "Address immediate safety and health needs, obtain corroborating financial and medical records, and report if additional evidence supports abuse or neglect.",
        "B": "Address immediate safety and health needs, clarify the concerns directly with the caregiver, and report if the explanation does not resolve the suspicion.",
        "C": "Address immediate safety and health needs, make the required protective report promptly based on reasonable suspicion, and coordinate appropriate client support.",
        "D": "Address immediate safety and health needs, arrange alternative care and practical resources, and allow the client to decide whether authorities are contacted."
      }
    },
    {
      "q_num": 14,
      "stem": "A client states an intention to die tonight, describes a specific suicide plan, has immediate access to the intended means, has already made preparations, and says, \u201cI don't think I can stop myself once I leave here.\u201d What should the therapist do FIRST?",
      "choices": {
        "A": "Maintain direct observation, initiate secure psychiatric transfer, and hand off the current suicide-risk findings directly to receiving staff.",
        "B": "Maintain direct observation, have a support person remove the identified means, and transport the client to same-day psychiatric urgent care.",
        "C": "Maintain direct observation, obtain emergency psychiatric consultation, and decide disposition after a fuller suicide-risk assessment.",
        "D": "Maintain direct observation, complete crisis safety planning, and discharge with continuous support to next-day psychiatric care."
      }
    },
    {
      "q_num": 15,
      "stem": "A therapist has been providing conjoint therapy to a married couple whose stated treatment purpose is reconciliation. One partner privately asks the therapist to stop couple sessions and continue seeing only that partner for individual therapy. The therapist has not yet discussed this proposed change with the other partner. What should the therapist do FIRST?",
      "choices": {
        "A": "In a meeting with the requesting partner, explore treatment goals, client-role concerns, and privacy issues before discussing any treatment change with both partners.",
        "B": "Pause conjoint treatment and meet separately with both partners to review client roles, informed consent, confidentiality expectations, records concerns, and whether either treatment structure remains clinically workable.",
        "C": "With both partners, review treatment purpose, client roles, privacy expectations, records implications, future communication expectations, and the informed agreement governing the proposed treatment structure.",
        "D": "Close conjoint treatment, review client-role and privacy implications with both partners, and consider later individual treatment only after a planned transition."
      }
    },
    {
      "q_num": 16,
      "stem": "A client communicates online with dozens of people each week but reports, \u201cWhen something actually goes wrong, I don\u2019t know who I could call.\u201d The client denies feeling socially isolated because \u201cI talk to people constantly.\u201d Which assessment is MOST appropriate?",
      "choices": {
        "A": "Count regular online and in-person contacts, frequency of interaction, network size, and whether the client has enough social opportunities each week.",
        "B": "Assess which relationships provide emotional support, practical help, reciprocity, dependable contact, and connection when the client experiences stress.",
        "C": "Assess loneliness, social anxiety, avoidance, comfort with in-person contact, and whether online interaction substitutes for relationships the client actually wants.",
        "D": "Identify the client\u2019s closest relationships, perceived intimacy, shared history, and whether those connections remain available when practical or emotional help is needed."
      }
    },
    {
      "q_num": 17,
      "stem": "In the fourth session of an interpersonal therapy group, two members disagree about one member repeatedly interrupting the other. Their voices rise slightly, but neither becomes threatening, and the group remains physically and emotionally safe. Several other members become quiet and look away. Which intervention should the group therapist use next?",
      "choices": {
        "A": "Invite the group to restate the disagreement as specific requests, use the exchange to identify what helps participation remain workable, and observe members\u2019 responses.",
        "B": "Invite the group to examine the conflict as it unfolds, process its effect on participation, and connect the current interaction to the group\u2019s relational work.",
        "C": "Invite the two members to connect their reactions to prior interpersonal patterns and ask the group what similar patterns they recognize.",
        "D": "Invite the group to repair the current tension, practice one shared strategy for disagreement, and generate additional responses for future sessions."
      }
    },
    {
      "q_num": 18,
      "stem": "A client spends hours reviewing routine work before sending it to colleagues. The client predicts, \u201cIf I do not check everything several times, I will miss something obvious and everyone will think I am incompetent.\u201d Repeated checking reduces anxiety briefly but is causing missed deadlines. The therapist is using CBT. Which intervention most directly tests the maintaining belief and behavior?",
      "choices": {
        "A": "Test the feared mistake prediction while eliminating repeated checking during one planned work task.",
        "B": "Record automatic thoughts before work tasks and generate balanced alternatives after each episode of repeated checking.",
        "C": "Create a graded hierarchy of increasingly difficult work tasks and practice each level while keeping checking available initially.",
        "D": "Review evidence for and against being incompetent and use coping statements whenever uncertainty triggers the urge to check."
      }
    },
    {
      "q_num": 19,
      "stem": "Six weeks after giving birth, a client reports that for the past four weeks she has experienced depressed mood nearly every day, marked loss of interest, feelings of worthlessness, impaired concentration, psychomotor slowing, decreased appetite, and insomnia even when the infant is asleep. The symptoms substantially interfere with functioning. There is no history of mania or hypomania. Which diagnosis is MOST supported?",
      "choices": {
        "A": "Adjustment disorder with depressed mood",
        "B": "Major depressive disorder",
        "C": "Persistent depressive disorder",
        "D": "Bipolar II disorder"
      }
    },
    {
      "q_num": 20,
      "stem": "A therapist strongly believes an adult client should leave a long-term relationship after repeated infidelity. The client has clearly stated that the current treatment goal is to decide what kind of relationship the client wants, not to be persuaded toward separation. The therapist notices becoming increasingly directive about ending the relationship. What should the therapist do?",
      "choices": {
        "A": "Use a neutral decisional-balance exercise, document the client's clinical treatment goals, compare staying and leaving, and keep recommendations limited to concerns the client identifies as relevant.",
        "B": "Discuss the value conflict openly, document the client's clinical treatment goals, and offer referral while continuing care long enough to assess whether the therapist can remain neutral.",
        "C": "Identify the therapist's value interference and return treatment to the client's stated goals, using consultation as needed for clinically neutral recommendations, document the treatment direction, and monitor whether later guidance remains aligned with the client's values.",
        "D": "Explore safety, trust, and relationship consequences under the client's clinical treatment goals, document the preferred direction, and avoid recommending either staying or leaving unless new concerns arise."
      }
    },
    {
      "q_num": 21,
      "stem": "For eight months, a 24-year-old has shown progressive social withdrawal, loss of motivation, and substantial decline in work functioning. During the most recent two months, the client has also experienced voices commenting on behavior, fixed persecutory beliefs, and disorganized speech. There have been no sustained major mood episodes, and substance and medical evaluation do not explain the disturbance. Which diagnosis is MOST supported?",
      "choices": {
        "A": "Schizoaffective disorder, depressive type",
        "B": "Major depressive disorder with psychotic features",
        "C": "Schizophrenia",
        "D": "Schizophreniform disorder"
      }
    },
    {
      "q_num": 22,
      "stem": "A graduate student seeks therapy for chronic procrastination. Most weeks, assignments are delayed until the deadline. The client reports one recent week when all work was completed early after studying at the library with a friend and dividing tasks into short blocks. The client says, \u201cI don\u2019t know why that week was different.\u201d The therapist is using a solution-oriented approach. Which intervention should the therapist use next?",
      "choices": {
        "A": "Examine the successful week in detail, identify what the client did differently, and choose one element to repeat before the next session.",
        "B": "Use the miracle question to describe a day with manageable procrastination, then identify one observable sign that the preferred future has begun.",
        "C": "Use coping questions to identify what has kept deadlines from being missed entirely, then build on one existing strength during the next assignment.",
        "D": "Offer a compliment for the early completion and use a scaling question to identify the next realistic increase in follow-through."
      }
    },
    {
      "q_num": 23,
      "stem": "A client attends therapy after an employer suggested using the company's employee-assistance program. During the first meeting, the client gives brief answers and says, \u201cHR thinks I have an anger problem. I don't know why I'm here.\u201d Which response would BEST support initial therapeutic engagement?",
      "choices": {
        "A": "Review the EAP purpose, confidentiality limits, and reporting expectations, then ask how the client understands the employer\u2019s concern.",
        "B": "Acknowledge the client\u2019s reluctance, ask what feels inaccurate about the referral, and clarify what the client does not want from therapy.",
        "C": "Acknowledge the referral and skepticism, invite the client\u2019s view of the concern, and identify any personally useful outcome from meeting.",
        "D": "Clarify that participation can be explored without accepting the employer\u2019s view, then ask whether the client prefers discussing the incident or current stress first."
      }
    },
    {
      "q_num": 24,
      "stem": "A client with chronic anxiety repeatedly texts a partner for reassurance whenever the partner is away. The client feels brief relief after each response, but the partner has become increasingly irritated and says the constant checking is straining the relationship. The client wants to change the pattern. Which intervention should the therapist use?",
      "choices": {
        "A": "Set one daily check-in and have the partner limit reassurance to that agreed time.",
        "B": "Explore separation fears and have the partner validate distress without repeatedly confirming that everything is okay.",
        "C": "Practice tolerating uncertainty by briefly delaying reassurance-seeking during separations while the partner responds consistently without repeated confirmation.",
        "D": "Track anxious predictions and partner responses for one week to identify the situations that most strongly trigger checking."
      }
    },
    {
      "q_num": 25,
      "stem": "A 16-year-old's close friend died suddenly. The adolescent reports guilt about not answering the friend's final text message, poor sleep, difficulty attending school, and feeling \u201cnumb.\u201d The adolescent denies current suicidal intent or plan. A supportive caregiver is available. What should the therapist prioritize?",
      "choices": {
        "A": "Assess grief plus suicide risk, involve the caregiver in support, and establish a structured school-attendance plan.",
        "B": "Assess grief plus suicide risk, involve the caregiver in support, and arrange an urgent crisis evaluation because of guilt.",
        "C": "Assess grief plus suicide risk, involve the caregiver in support, and use structured retelling to address guilt.",
        "D": "Assess grief plus suicide risk, involve caregiver support, and provide age-appropriate grief care with functional restoration."
      }
    }
  ]
}
JSON;

$data = json_decode( $json, true );
if ( ! is_array( $data ) || empty( $data['questions'] ) ) {
	fwrite( STDERR, "Invalid JSON payload\n" );
	exit( 1 );
}

$out = "<?php\n";
$out .= "/**\n";
$out .= " * LMFT California Clinical — Comprehensive Simulation Form A items 1–25 (PROMPT 01).\n";
$out .= " * Learner-facing stems and choices only. Answer keys deferred to admin merge.\n";
$out .= " *\n";
$out .= " * @package CTA_LMS\n";
$out .= " */\n";
$out .= "if ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n\n";
$out .= "return array(\n";

foreach ( $data['questions'] as $row ) {
	$num = (int) ( $row['q_num'] ?? 0 );
	$stem = (string) ( $row['stem'] ?? '' );
	$choices = isset( $row['choices'] ) && is_array( $row['choices'] ) ? $row['choices'] : array();

	$out .= "\tarray(\n";
	$out .= "\t\t'question_code'  => 'CTA-LMFT-CA-FA-" . str_pad( (string) $num, 3, '0', STR_PAD_LEFT ) . "',\n";
	$out .= "\t\t'question_text'  => " . var_export( $stem, true ) . ",\n";
	$out .= "\t\t'option_a'       => " . var_export( (string) ( $choices['A'] ?? '' ), true ) . ",\n";
	$out .= "\t\t'option_b'       => " . var_export( (string) ( $choices['B'] ?? '' ), true ) . ",\n";
	$out .= "\t\t'option_c'       => " . var_export( (string) ( $choices['C'] ?? '' ), true ) . ",\n";
	$out .= "\t\t'option_d'       => " . var_export( (string) ( $choices['D'] ?? '' ), true ) . ",\n";
	$out .= "\t\t'correct_option' => 'x',\n";
	$out .= "\t\t'explanation'    => '',\n";
	$out .= "\t),\n";
}

$out .= ");\n";

$target = $root . '/includes/quiz-seeds/lmft-clinical-form-a-items-01-25.php';
file_put_contents( $target, $out );
echo "Wrote {$target}\n";
